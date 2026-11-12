<?php

namespace App\Models;

use App\Enums\RefundStatus;
use App\Models\Concerns\BelongsToCustomer;
use App\Services\Money;
use Database\Factories\RefundFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

/**
 * A refund request on a collected payment (four-eyes: the requester can never approve it). Only the refund
 * actions in App\Actions\Payments change its status; the payment itself changes through Payment::transitionTo().
 * Refunds are part of the financial record and are never deleted.
 */
#[Fillable(['payment_id', 'customer_id', 'gateway', 'amount_minor', 'currency', 'reason', 'requested_by'])]
class Refund extends Model
{
    /** @use HasFactory<RefundFactory> */
    use BelongsToCustomer, HasFactory, HasUlids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'requested',
    ];

    protected static function booted(): void
    {
        static::created(function (Refund $refund): void {
            $refund->forceFill(['reference' => sprintf('RF-%d-%05d', $refund->created_at?->year ?? now()->year, $refund->getKey())])->saveQuietly();
        });

        static::deleting(fn () => throw new LogicException('Refunds are part of the financial record.'));
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
            'status' => RefundStatus::class,
            'amount_minor' => 'integer',
            'decided_at' => 'datetime',
            'processed_at' => 'datetime',
            'completed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    /**
     * @return HasOne<CreditNote, $this>
     */
    public function creditNote(): HasOne
    {
        return $this->hasOne(CreditNote::class);
    }

    public function formattedAmount(): string
    {
        return Money::localized($this->amount_minor, $this->currency);
    }

    public function isFull(): bool
    {
        return $this->payment !== null && $this->amount_minor === $this->payment->amount_minor;
    }
}
