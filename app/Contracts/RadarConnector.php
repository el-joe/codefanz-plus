<?php

namespace App\Contracts;

use App\Exceptions\RadarConnectorException;
use App\Services\Radar\RadarProjectData;

/**
 * A compliant collector for one radar platform (official API). Email-alert platforms are push-based and are fed
 * through the inbound n8n endpoint instead.
 */
interface RadarConnector
{
    public function isConfigured(): bool;

    /**
     * The newest open projects matching the configured query.
     *
     * @return list<RadarProjectData>
     *
     * @throws RadarConnectorException
     */
    public function fetch(int $limit): array;
}
