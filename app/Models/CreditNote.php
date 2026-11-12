<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCustomer;
use App\Services\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Credit note for a completed refund (one per refund, numbered CN-YYYY-NNNNN gap-free), with the customer's and
 * the seller's details frozen at issue time. Immutable.
 */
#[Fillable(['refund_id', 'payment_id', 'number', 'customer_id', 'customer_snapshot', 'seller_snapshot', 'amount_minor', 'currency', 'reason', 'issued_at'])]
class CreditNote extends Model
{
    use BelongsToCustomer, HasUlids;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Credit notes are immutable.'));
        static::deleting(fn () => throw new LogicException('Credit notes are immutable.'));
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
        return ['customer_snapshot' => 'array', 'seller_snapshot' => 'array', 'amount_minor' => 'integer', 'issued_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Refund, $this>
     */
    public function refund(): BelongsTo
    {
        return $this->belongsTo(Refund::class);
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function formattedAmount(): string
    {
        return Money::localized($this->amount_minor, $this->currency);
    }
}
