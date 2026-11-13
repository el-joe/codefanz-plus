<?php

namespace App\Services\Radar\Parsers;

/**
 * PeoplePerHour job alert emails. Job pages look like
 * https://www.peopleperhour.com/freelance-jobs/<category>/<sub>/<slug>-4213567 (the trailing number is the id);
 * budgets are fixed ("£600") or hourly ("$25/hr").
 */
class PeoplePerHourAlertParser extends AlertEmailParser
{
    protected function urlPattern(): string
    {
        return '~^https?://(?:www\.)?peopleperhour\.com/freelance-jobs/(?:[^?#\s]*?-)?(\d{4,})(?:[/?#]|$)~i';
    }

    protected function defaultCurrency(): string
    {
        return 'GBP';
    }

    public function senderDomains(): array
    {
        return ['peopleperhour.com'];
    }
}
