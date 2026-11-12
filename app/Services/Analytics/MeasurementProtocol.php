<?php

namespace App\Services\Analytics;

use App\Enums\LeadSource;
use App\Jobs\SendAnalyticsEvent;
use App\Models\Lead;
use App\Services\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Server-side GA4 Measurement Protocol events for public lead submissions. Queued only when the measurement ID and
 * API secret are saved AND the visitor opted in to analytics cookies. No personal data is sent: the event carries
 * the lead source only, and the client id comes from the visitor's own _ga cookie (random when absent).
 */
class MeasurementProtocol
{
    public const ENDPOINT = 'https://www.google-analytics.com/mp/collect';

    /**
     * @var list<LeadSource>
     */
    private const PUBLIC_SOURCES = [LeadSource::QuoteForm, LeadSource::ContactForm, LeadSource::AiAssistant];

    public function __construct(private Settings $settings, private AnalyticsConsent $consent) {}

    public function isConfigured(): bool
    {
        return $this->consent->measurementId() !== null && $this->settings->has('integrations', 'ga4_api_secret');
    }

    /**
     * Decide in the visitor's request (consent lives in their cookie), send from the queue.
     */
    public function leadSubmitted(Lead $lead, Request $request): bool
    {
        if (! in_array($lead->source, self::PUBLIC_SOURCES, true) || ! $this->isConfigured() || ! $this->consent->allowsAnalytics($request)) {
            return false;
        }

        SendAnalyticsEvent::dispatch($this->clientId($request), 'generate_lead', ['lead_source' => $lead->source->value]);

        return true;
    }

    /**
     * @param  array<string, scalar>  $params
     */
    public function send(string $clientId, string $event, array $params): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        return Http::timeout(5)->connectTimeout(3)->acceptJson()->post(self::ENDPOINT.'?'.http_build_query([
            'measurement_id' => $this->consent->measurementId(),
            'api_secret' => (string) $this->settings->get('integrations', 'ga4_api_secret'),
        ]), [
            'client_id' => $clientId,
            'non_personalized_ads' => true,
            'events' => [['name' => $event, 'params' => [...$params, 'engagement_time_msec' => 1]]],
        ])->successful();
    }

    /**
     * "GA1.1.123456789.1700000000" → "123456789.1700000000"; a random id when there is no _ga cookie.
     */
    public function clientId(Request $request): string
    {
        $ga = (string) $request->cookie('_ga');

        if (preg_match('/^GA\d\.\d\.(\d+\.\d+)$/', $ga, $matches) === 1) {
            return $matches[1];
        }

        return random_int(100000000, 999999999).'.'.now()->getTimestamp();
    }
}
