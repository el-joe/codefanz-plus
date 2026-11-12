<?php

namespace App\Services\Analytics;

use App\Services\Settings;
use Illuminate\Http\Request;

/**
 * Visitor cookie consent for analytics. Only necessary cookies are used until the visitor opts in; the choice is a
 * first-party cookie (no third-party consent platform). GA4 is loaded only with a measurement ID AND consent.
 */
class AnalyticsConsent
{
    public const COOKIE = 'cf_consent';

    public const ANALYTICS = 'analytics';

    public const NECESSARY = 'necessary';

    public const LIFETIME_DAYS = 180;

    public function __construct(private Settings $settings) {}

    public function measurementId(): ?string
    {
        $id = $this->settings->get('integrations', 'ga4_measurement_id');

        return is_string($id) && preg_match('/^G-[A-Z0-9]{4,20}$/', $id) === 1 ? $id : null;
    }

    /**
     * The banner is only needed when there is something to consent to (GA4 configured).
     */
    public function isNeeded(): bool
    {
        return $this->measurementId() !== null;
    }

    public function choice(Request $request): ?string
    {
        $value = $request->cookie(self::COOKIE);

        return in_array($value, [self::ANALYTICS, self::NECESSARY], true) ? $value : null;
    }

    public function hasDecided(Request $request): bool
    {
        return $this->choice($request) !== null;
    }

    public function allowsAnalytics(Request $request): bool
    {
        return $this->choice($request) === self::ANALYTICS;
    }

    public function shouldLoadGa4(Request $request): bool
    {
        return $this->measurementId() !== null && $this->allowsAnalytics($request);
    }
}
