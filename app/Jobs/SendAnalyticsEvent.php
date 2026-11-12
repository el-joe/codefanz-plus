<?php

namespace App\Jobs;

use App\Services\Analytics\MeasurementProtocol;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Delivers one consented GA4 Measurement Protocol event. Failures are logged and never retried aggressively:
 * analytics must not affect lead handling.
 */
class SendAnalyticsEvent implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 15;

    /**
     * @param  array<string, scalar>  $params
     */
    public function __construct(public string $clientId, public string $event, public array $params = []) {}

    public function handle(MeasurementProtocol $protocol): void
    {
        try {
            $protocol->send($this->clientId, $this->event, $this->params);
        } catch (Throwable $exception) {
            Log::warning('GA4 Measurement Protocol event failed.', ['event' => $this->event, 'error' => mb_substr($exception->getMessage(), 0, 200)]);
        }
    }
}
