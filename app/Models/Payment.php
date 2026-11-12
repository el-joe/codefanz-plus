<?php

namespace App\Models;

use App\Enums\PaymentEventSource;
use App\Enums\PaymentStatus;
use App\Models\Concerns\BelongsToCustomer;
use App\Services\Money;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

/**
 * One payment attempt through a gateway (immutable ledger, no soft deletes).
 *
 * The status is not mass assignable and can only change through transitionTo(), which the payment actions call
 * inside a transaction holding a row lock; it records the change in payment_events. Only VerifyPayment moves a
 * payment to Paid, after the gateway confirmed it server-side.
 */
#[Fillable([
    'payment_link_id', 'customer_id', 'project_id', 'milestone_id', 'gateway', 'amount_minor', 'currency', 'metadata',
])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use BelongsToCustomer, HasFactory, HasUlids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
        'refunded_minor' => 0,
    ];

    private bool $transitioning = false;

    protected static function booted(): void
    {
        static::created(function (Payment $payment): void {
            $payment->forceFill(['reference' => sprintf('PAY-%d-%05d', $payment->created_at?->year ?? now()->year, $payment->getKey())])->saveQuietly();
        });

        static::updating(function (Payment $payment): void {
            if ($payment->isDirty('status') && ! $payment->transitioning) {
                throw new LogicException('Payment status changes only through Payment::transitionTo() in a payment action.');
            }
        });

        static::deleting(fn () => throw new LogicException('Payments are part of the immutable ledger.'));
    }

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'verified_via' => PaymentEventSource::class,
            'amount_minor' => 'integer',
            'fee_minor' => 'integer',
            'net_minor' => 'integer',
            'refunded_minor' => 'integer',
            'paid_at' => 'datetime',
            'verified_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * Change the status (and any extra columns) and append the transition to payment_events. Call it only from a
     * payment action, inside a transaction that locked this row with lockForUpdate().
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>|null  $payload
     */
    public function transitionTo(
        PaymentStatus $to,
        PaymentEventSource $source,
        array $attributes = [],
        ?array $payload = null,
        ?WebhookEvent $webhookEvent = null,
        ?User $user = null,
    ): PaymentEvent {
        $from = $this->status;

        $this->transitioning = true;

        try {
            $this->forceFill([...$attributes, 'status' => $to])->save();
        } finally {
            $this->transitioning = false;
        }

        return $this->events()->create([
            'from_status' => $from?->value,
            'to_status' => $to->value,
            'source' => $source,
            'webhook_event_id' => $webhookEvent?->getKey(),
            'user_id' => $user?->getKey(),
            'payload' => $payload,
        ]);
    }

    /**
     * @return BelongsTo<PaymentLink, $this>
     */
    public function link(): BelongsTo
    {
        return $this->belongsTo(PaymentLink::class, 'payment_link_id');
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Milestone, $this>
     */
    public function milestone(): BelongsTo
    {
        return $this->belongsTo(Milestone::class);
    }

    /**
     * @return HasMany<PaymentEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(PaymentEvent::class)->orderBy('id');
    }

    /**
     * @return HasOne<Receipt, $this>
     */
    public function receipt(): HasOne
    {
        return $this->hasOne(Receipt::class);
    }

    public function formattedAmount(): string
    {
        return Money::localized($this->amount_minor, $this->currency);
    }

    /**
     * Collected amount after refunds; zero unless the payment was collected.
     */
    public function collectedMinor(): int
    {
        return $this->status?->isCollected() ? max(0, $this->amount_minor - $this->refunded_minor) : 0;
    }

    public function gatewayLabel(): string
    {
        return (string) (config("codefanz.payments.gateways.{$this->gateway}.label") ?? $this->gateway);
    }

    public function methodLabel(): string
    {
        $method = $this->metadata['payment_method_type'] ?? null;

        return match ($method) {
            'card' => __('Card'),
            'wallet', 'mobile_wallet' => __('Wallet'),
            null => __('Online payment'),
            default => __(ucfirst(str_replace('_', ' ', (string) $method))),
        };
    }
}
