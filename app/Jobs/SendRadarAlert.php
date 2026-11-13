<?php

namespace App\Jobs;

use App\Models\RadarAlert;
use App\Services\Radar\RadarAlerts;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Sends one immediate / normal Telegram alert. A failure is recorded on the alert (shown in the radar) rather than
 * retried, so a broken bot never floods the chat later.
 */
class SendRadarAlert implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public int $alertId) {}

    public function handle(RadarAlerts $alerts): void
    {
        $alert = RadarAlert::query()->find($this->alertId);

        if ($alert !== null) {
            $alerts->sendNow($alert);
        }
    }
}
