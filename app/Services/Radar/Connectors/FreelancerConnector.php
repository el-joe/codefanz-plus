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
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Freelancer.com official REST API: GET /projects/0.1/projects/active/ (active projects search) authenticated
 * with the account's OAuth token (freelancer-oauth-v1 header). Read-only; nothing is ever posted.
 */
class FreelancerConnector implements RadarConnector
{
    public function __construct(private Settings $settings) {}

    public function isConfigured(): bool
    {
        return $this->settings->has('radar', 'freelancer_api_token');
    }

    public function fetch(int $limit): array
    {
        $token = $this->settings->get('radar', 'freelancer_api_token');

        if (! is_string($token) || $token === '') {
            throw new RadarConnectorException(__('Freelancer API token is not configured.'));
        }

        try {
            $response = Http::timeout((int) config('codefanz.radar.timeout_seconds', 15))
                ->acceptJson()
                ->withHeaders(['freelancer-oauth-v1' => $token])
                ->get(rtrim((string) config('codefanz.radar.freelancer.base_url'), '/').'/projects/0.1/projects/active/', array_filter([
                    'query' => (string) $this->settings->get('radar', 'freelancer_query', ''),
                    'limit' => min(100, max(1, $limit)),
                    'sort_field' => 'time_updated',
                    'compact' => 'true',
                    'full_description' => 'true',
                    'job_details' => 'true',
                    'user_details' => 'true',
                    'user_country_details' => 'true',
                    'user_reputation' => 'true',
                    'user_status' => 'true',
                ], fn ($value): bool => $value !== ''));
        } catch (ConnectionException) {
            throw new RadarConnectorException(__('Freelancer API could not be reached.'));
        }

        if ($response->failed() || $response->json('status') !== 'success') {
            throw new RadarConnectorException(__('Freelancer API error (HTTP :status).', ['status' => $response->status()]), $response->status());
        }

        return $this->parse((array) $response->json('result', []));
    }

    /**
     * @param  array<string, mixed>  $result
     * @return list<RadarProjectData>
     */
    public function parse(array $result): array
    {
        $users = (array) ($result['users'] ?? []);
        $projects = [];

        foreach ((array) ($result['projects'] ?? []) as $project) {
            if (! is_array($project) || ! isset($project['id'], $project['title'])) {
                continue;
            }

            $owner = (array) ($users[(string) ($project['owner_id'] ?? '')] ?? []);
            $currency = Str::upper((string) data_get($project, 'currency.code', '')) ?: null;
            $min = data_get($project, 'budget.minimum');
            $max = data_get($project, 'budget.maximum');
            $country = data_get($owner, 'location.country.code');
            $rating = data_get($owner, 'reputation.entire_history.overall');
            $submitted = data_get($project, 'time_submitted');

            $projects[] = new RadarProjectData(
                externalId: (string) $project['id'],
                url: 'https://www.freelancer.com/projects/'.ltrim((string) ($project['seo_url'] ?? $project['id']), '/'),
                title: RadarText::clean((string) $project['title'], 255),
                description: RadarText::clean((string) ($project['description'] ?? $project['preview_description'] ?? '')),
                budgetMinMinor: is_numeric($min) ? (int) round($min * 100) : null,
                budgetMaxMinor: is_numeric($max) ? (int) round($max * 100) : null,
                currency: $currency,
                budgetType: match ($project['type'] ?? null) {
                    'fixed' => RadarBudgetType::Fixed,
                    'hourly' => RadarBudgetType::Hourly,
                    default => RadarBudgetType::Unknown,
                },
                skills: array_values(array_filter(array_map(fn ($job): string => trim((string) data_get($job, 'name', '')), (array) ($project['jobs'] ?? [])), 'filled')),
                clientCountry: is_string($country) && strlen($country) === 2 ? Str::upper($country) : null,
                clientRating: is_numeric($rating) ? round((float) $rating, 2) : null,
                clientPaymentVerified: is_bool($verified = data_get($owner, 'status.payment_verified')) ? $verified : null,
                proposalsCount: is_numeric($bids = data_get($project, 'bid_stats.bid_count')) ? (int) $bids : null,
                postedAt: is_numeric($submitted) ? CarbonImmutable::createFromTimestamp((int) $submitted) : null,
                source: ['api' => 'freelancer', 'project' => $project],
            );
        }

        return $projects;
    }
}
