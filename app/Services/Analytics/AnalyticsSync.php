<?php

namespace App\Services\Analytics;

use App\Exceptions\GoogleApiException;
use App\Models\AnalyticsSnapshot;
use App\Services\Settings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Daily pull of the GA4 Data API and Search Console reports into analytics_snapshots (one row per report per day).
 * The last outcome per source is kept in Settings (analytics_status) so the dashboard can show "connected",
 * "reconnect" or "error" honestly, alongside the last cached data.
 */
class AnalyticsSync
{
    public const GA4_ENDPOINT = 'https://analyticsdata.googleapis.com/v1beta/properties/%s:runReport';

    public const GSC_ENDPOINT = 'https://www.googleapis.com/webmasters/v3/sites/%s/searchAnalytics/query';

    /**
     * Days of daily data pulled (two 28-day periods for comparisons).
     */
    public const DAILY_DAYS = 56;

    public const TOP_DAYS = 28;

    public function __construct(private GoogleServiceAccount $google, private Settings $settings) {}

    public function ga4PropertyId(): ?string
    {
        $id = $this->settings->get('integrations', 'ga4_property_id');

        return is_string($id) && preg_match('/^\d{4,20}$/', $id) === 1 ? $id : null;
    }

    public function searchConsoleSite(): ?string
    {
        $site = $this->settings->get('integrations', 'gsc_site_url');

        return is_string($site) && $site !== '' ? $site : null;
    }

    /**
     * @return array<string, string> source => outcome ("ok", "skipped" or the error message)
     */
    public function run(): array
    {
        return [
            AnalyticsSnapshot::SOURCE_GA4 => $this->syncSource(AnalyticsSnapshot::SOURCE_GA4, $this->ga4PropertyId() !== null, fn () => $this->syncGa4()),
            AnalyticsSnapshot::SOURCE_GSC => $this->syncSource(AnalyticsSnapshot::SOURCE_GSC, $this->searchConsoleSite() !== null, fn () => $this->syncSearchConsole()),
        ];
    }

    /**
     * @return array{state: string, message: ?string, at: ?string}|null
     */
    public function status(string $source): ?array
    {
        $status = $this->settings->get('analytics_status', $source);

        return is_array($status) ? $status : null;
    }

    private function syncSource(string $source, bool $configured, callable $sync): string
    {
        if (! $configured || ! $this->google->isConfigured()) {
            return 'skipped';
        }

        try {
            $sync();
            $this->settings->set('analytics_status', $source, ['state' => 'ok', 'message' => null, 'at' => now()->toIso8601String()]);

            return 'ok';
        } catch (GoogleApiException $exception) {
            $this->settings->set('analytics_status', $source, ['state' => $exception->needsReconnect ? 'reconnect' : 'error', 'message' => mb_substr($exception->getMessage(), 0, 200), 'at' => now()->toIso8601String()]);

            return $exception->getMessage();
        } catch (Throwable $exception) {
            $this->settings->set('analytics_status', $source, ['state' => 'error', 'message' => __('Google could not be reached.'), 'at' => now()->toIso8601String()]);

            return $exception->getMessage();
        }
    }

    private function syncGa4(): void
    {
        $token = $this->google->accessToken([GoogleServiceAccount::SCOPE_ANALYTICS]);
        $url = sprintf(self::GA4_ENDPOINT, $this->ga4PropertyId());
        $daily = ['startDate' => self::DAILY_DAYS.'daysAgo', 'endDate' => 'yesterday'];
        $top = ['startDate' => self::TOP_DAYS.'daysAgo', 'endDate' => 'yesterday'];

        $reports = [
            'daily' => ['dateRanges' => [$daily], 'dimensions' => [['name' => 'date']], 'metrics' => [['name' => 'activeUsers'], ['name' => 'sessions'], ['name' => 'screenPageViews']], 'orderBys' => [['dimension' => ['dimensionName' => 'date']]], 'limit' => 100],
            'channels' => ['dateRanges' => [$top], 'dimensions' => [['name' => 'sessionDefaultChannelGroup']], 'metrics' => [['name' => 'sessions']], 'orderBys' => [['metric' => ['metricName' => 'sessions'], 'desc' => true]], 'limit' => 8],
            'landing_pages' => ['dateRanges' => [$top], 'dimensions' => [['name' => 'landingPage']], 'metrics' => [['name' => 'sessions']], 'orderBys' => [['metric' => ['metricName' => 'sessions'], 'desc' => true]], 'limit' => 10],
            'events' => ['dateRanges' => [$top], 'dimensions' => [['name' => 'eventName']], 'metrics' => [['name' => 'eventCount']], 'orderBys' => [['metric' => ['metricName' => 'eventCount'], 'desc' => true]], 'limit' => 10],
        ];

        foreach ($reports as $key => $body) {
            $response = Http::withToken($token)->acceptJson()->timeout(20)->connectTimeout(5)->post($url, $body);

            if (! $response->successful()) {
                throw GoogleApiException::requestFailed($response->status());
            }

            $dimensions = array_column($body['dimensions'], 'name');
            $metrics = array_column($body['metrics'], 'name');
            $rows = collect($response->json('rows') ?? [])->map(function (array $row) use ($dimensions, $metrics): array {
                $values = [];

                foreach ($dimensions as $index => $name) {
                    $value = (string) ($row['dimensionValues'][$index]['value'] ?? '');
                    $values[$name] = $name === 'date' && strlen($value) === 8 ? Carbon::createFromFormat('Ymd', $value)->toDateString() : $value;
                }

                foreach ($metrics as $index => $name) {
                    $values[$name] = (float) ($row['metricValues'][$index]['value'] ?? 0);
                }

                return $values;
            })->all();

            $this->store(AnalyticsSnapshot::SOURCE_GA4, $key, $body['dateRanges'][0], $rows);
        }
    }

    private function syncSearchConsole(): void
    {
        $token = $this->google->accessToken([GoogleServiceAccount::SCOPE_SEARCH_CONSOLE]);
        $url = sprintf(self::GSC_ENDPOINT, rawurlencode((string) $this->searchConsoleSite()));
        $end = today()->subDay()->toDateString();
        $daily = ['startDate' => today()->subDays(self::DAILY_DAYS)->toDateString(), 'endDate' => $end];
        $top = ['startDate' => today()->subDays(self::TOP_DAYS)->toDateString(), 'endDate' => $end];

        $reports = [
            'daily' => [...$daily, 'dimensions' => ['date'], 'rowLimit' => 100],
            'queries' => [...$top, 'dimensions' => ['query'], 'rowLimit' => 10],
            'pages' => [...$top, 'dimensions' => ['page'], 'rowLimit' => 10],
            'countries' => [...$top, 'dimensions' => ['country'], 'rowLimit' => 10],
            'devices' => [...$top, 'dimensions' => ['device'], 'rowLimit' => 5],
        ];

        foreach ($reports as $key => $body) {
            $response = Http::withToken($token)->acceptJson()->timeout(20)->connectTimeout(5)->post($url, $body);

            if (! $response->successful()) {
                throw GoogleApiException::requestFailed($response->status());
            }

            $rows = collect($response->json('rows') ?? [])->map(fn (array $row): array => [
                'key' => (string) ($row['keys'][0] ?? ''),
                'clicks' => (float) ($row['clicks'] ?? 0),
                'impressions' => (float) ($row['impressions'] ?? 0),
                'ctr' => (float) ($row['ctr'] ?? 0),
                'position' => (float) ($row['position'] ?? 0),
            ])->all();

            $this->store(AnalyticsSnapshot::SOURCE_GSC, $key, ['startDate' => $body['startDate'], 'endDate' => $body['endDate']], $rows);
        }
    }

    /**
     * @param  array<string, string>  $range
     * @param  list<array<string, mixed>>  $rows
     */
    private function store(string $source, string $key, array $range, array $rows): void
    {
        AnalyticsSnapshot::query()->updateOrCreate(
            ['source' => $source, 'report_key' => $key, 'date' => today()->toDateString()],
            ['dimensions' => $range, 'values' => $rows, 'fetched_at' => now()],
        );
    }
}
