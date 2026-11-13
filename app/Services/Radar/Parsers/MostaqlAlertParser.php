<?php

namespace App\Services\Radar\Parsers;

/**
 * Mostaql saved-search alert emails (Arabic). Project pages look like https://mostaql.com/project/123456-slug and
 * budgets are stated in US dollars ("$250.00 - $500.00").
 */
class MostaqlAlertParser extends AlertEmailParser
{
    protected function urlPattern(): string
    {
        return '~^https?://(?:www\.)?mostaql\.com/projects?/(\d+)~i';
    }

    protected function defaultCurrency(): string
    {
        return 'USD';
    }

    public function senderDomains(): array
    {
        return ['mostaql.com'];
    }
}
