<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Every webhook delivery we received. UNIQUE(gateway, event_id) turns duplicate deliveries into no-ops.
 *
 * The received data (gateway, event id, type, signature result, payload) is immutable; only the processing
 * bookkeeping (attempts, processed_at, error) may change, and rows are never deleted.
 */
#[Fillable(['gateway', 'event_id', 'event_type', 'signature_valid', 'payload', 'attempts', 'processed_at', 'error'])]
class WebhookEvent extends Model
{
    /**
     * @var list<string>
     */
    private const BOOKKEEPING = ['attempts', 'processed_at', 'error', 'updated_at'];

    protected static function booted(): void
    {
        static::updating(function (WebhookEvent $event): void {
            if (array_diff(array_keys($event->getDirty()), self::BOOKKEEPING) !== []) {
                throw new LogicException('Received webhook data is immutable.');
            }
        });

        static::deleting(fn () => throw new LogicException('Webhook events are append-only.'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'signature_valid' => 'boolean',
            'payload' => 'array',
            'attempts' => 'integer',
            'processed_at' => 'datetime',
        ];
    }

    public function isProcessed(): bool
    {
        return $this->processed_at !== null;
    }
}
