<?php

namespace App\Services\Radar\Connectors;

use App\Contracts\RadarConnector;
use App\Enums\RadarBudgetType;
use App\Exceptions\RadarConnectorException;
use App\Services\Radar\RadarProjectData;
use App\Services\Radar\RadarText;
use App\Services\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Upwork official GraphQL API (marketplaceJobPostingsSearch). Needs an approved API key: the client credentials
 * grant returns a short-lived access token (cached as a plain string, never logged). Until a key is approved and
 * saved, the platform stays "Requires credentials". Verify the query fields against Upwork's current schema when
 * the key is issued.
 */
class UpworkConnector implements RadarConnector
{
    private const QUERY = <<<'GRAPHQL'
query RadarJobs($filter: MarketplaceJobPostingsSearchFilter, $sort: [MarketplaceJobPostingSearchSortAttribute]) {
  marketplaceJobPostingsSearch(marketPlaceJobFilter: $filter, searchType: USER_JOBS_SEARCH, sortAttributes: $sort) {
    edges {
      node {
        id
        ciphertext
        title
        description
        createdDateTime
        totalApplicants
        skills { name prettyName }
        amount { rawValue currency }
        hourlyBudgetMin { rawValue currency }
        hourlyBudgetMax { rawValue currency }
        client { location { country } totalFeedback totalSpent { rawValue currency } verificationStatus }
      }
    }
  }
}
GRAPHQL;

    public function __construct(private Settings $settings) {}

    public function isConfigured(): bool
    {
        return $this->settings->has('radar', 'upwork_client_id') && $this->settings->has('radar', 'upwork_client_secret');
    }

    public function fetch(int $limit): array
    {
        $token = $this->accessToken();

        try {
            $response = Http::timeout((int) config('codefanz.radar.timeout_seconds', 15))
                ->acceptJson()
                ->withToken($token)
                ->post((string) config('codefanz.radar.upwork.graphql_url'), [
                    'query' => self::QUERY,
                    'variables' => [
                        'filter' => array_filter([
                            'searchExpression_eq' => (string) $this->settings->get('radar', 'upwork_query', ''),
                            'pagination_eq' => ['first' => min(50, max(1, $limit))],
                        ], fn ($value): bool => $value !== ''),
                        'sort' => [['field' => 'RECENCY']],
                    ],
                ]);
        } catch (ConnectionException) {
            throw new RadarConnectorException(__('Upwork API could not be reached.'));
        }

        if ($response->status() === 401) {
            Cache::forget($this->tokenCacheKey());
        }

        if ($response->failed() || filled($response->json('errors'))) {
            throw new RadarConnectorException(__('Upwork API error (HTTP :status).', ['status' => $response->status()]), $response->status());
        }

        return $this->parse((array) $response->json('data.marketplaceJobPostingsSearch.edges', []));
    }

    /**
     * @param  list<mixed>  $edges
     * @return list<RadarProjectData>
     */
    public function parse(array $edges): array
    {
        $projects = [];

        foreach ($edges as $edge) {
            $job = (array) data_get($edge, 'node', []);

            if (! isset($job['id'], $job['title'])) {
                continue;
            }

            $fixed = data_get($job, 'amount.rawValue');
            $hourlyMin = data_get($job, 'hourlyBudgetMin.rawValue');
            $hourlyMax = data_get($job, 'hourlyBudgetMax.rawValue');
            $isHourly = is_numeric($hourlyMin) || is_numeric($hourlyMax);
            $hasFixed = is_numeric($fixed) && (float) $fixed > 0;
            $currency = Str::upper((string) (data_get($job, $isHourly ? 'hourlyBudgetMin.currency' : 'amount.currency') ?: 'USD'));
            $country = (string) data_get($job, 'client.location.country', '');
            $rating = data_get($job, 'client.totalFeedback');
            $spent = data_get($job, 'client.totalSpent.rawValue');
            $created = data_get($job, 'createdDateTime');

            $projects[] = new RadarProjectData(
                externalId: (string) $job['id'],
                url: 'https://www.upwork.com/jobs/'.($job['ciphertext'] ?? $job['id']),
                title: RadarText::clean((string) $job['title'], 255),
                description: RadarText::clean((string) ($job['description'] ?? '')),
                budgetMinMinor: $isHourly ? (is_numeric($hourlyMin) ? (int) round($hourlyMin * 100) : null) : ($hasFixed ? (int) round($fixed * 100) : null),
                budgetMaxMinor: $isHourly ? (is_numeric($hourlyMax) ? (int) round($hourlyMax * 100) : null) : ($hasFixed ? (int) round($fixed * 100) : null),
                currency: $isHourly || $hasFixed ? $currency : null,
                budgetType: $isHourly ? RadarBudgetType::Hourly : ($hasFixed ? RadarBudgetType::Fixed : RadarBudgetType::Unknown),
                skills: array_values(array_filter(array_map(fn ($skill): string => trim((string) (data_get($skill, 'prettyName') ?: data_get($skill, 'name', ''))), (array) ($job['skills'] ?? [])), 'filled')),
                clientCountry: strlen($country) === 2 ? Str::upper($country) : null,
                clientRating: is_numeric($rating) ? round((float) $rating, 2) : null,
                clientSpentMinor: is_numeric($spent) ? (int) round($spent * 100) : null,
                clientPaymentVerified: ($status = data_get($job, 'client.verificationStatus')) !== null ? $status === 'VERIFIED' : null,
                proposalsCount: is_numeric($applicants = data_get($job, 'totalApplicants')) ? (int) $applicants : null,
                postedAt: is_string($created) && $created !== '' ? CarbonImmutable::parse($created) : null,
                source: ['api' => 'upwork', 'job' => $job],
            );
        }

        return $projects;
    }

    private function accessToken(): string
    {
        $clientId = $this->settings->get('radar', 'upwork_client_id');
        $secret = $this->settings->get('radar', 'upwork_client_secret');

        if (! is_string($clientId) || $clientId === '' || ! is_string($secret) || $secret === '') {
            throw new RadarConnectorException(__('Upwork API key is not configured.'));
        }

        $cached = Cache::get($this->tokenCacheKey());

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        try {
            $response = Http::timeout((int) config('codefanz.radar.timeout_seconds', 15))
                ->asForm()
                ->acceptJson()
                ->post((string) config('codefanz.radar.upwork.token_url'), [
                    'grant_type' => 'client_credentials',
                    'client_id' => $clientId,
                    'client_secret' => $secret,
                ]);
        } catch (ConnectionException) {
            throw new RadarConnectorException(__('Upwork API could not be reached.'));
        }

        $token = $response->json('access_token');

        if ($response->failed() || ! is_string($token) || $token === '') {
            throw new RadarConnectorException(__('Upwork rejected the API key (HTTP :status).', ['status' => $response->status()]), $response->status());
        }

        Cache::put($this->tokenCacheKey(), $token, now()->addSeconds(max(60, (int) $response->json('expires_in', 3600) - 60)));

        return $token;
    }

    private function tokenCacheKey(): string
    {
        return 'radar.upwork.token.'.sha1((string) $this->settings->get('radar', 'upwork_client_id'));
    }
}
