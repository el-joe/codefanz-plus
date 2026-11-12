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
 * Receipt for a verified payment (one per payment, numbered RCP-YYYY-NNNNN). Issued by VerifyPayment.
 */
#[Fillable(['payment_id', 'number', 'customer_id', 'customer_snapshot', 'seller_snapshot', 'amount_minor', 'currency', 'issued_at'])]
class Receipt extends Model
{
    use BelongsToCustomer, HasUlids;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Receipts are immutable.'));
        static::deleting(fn () => throw new LogicException('Receipts are immutable.'));
    }

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['customer_snapshot' => 'array', 'seller_snapshot' => 'array', 'amount_minor' => 'integer', 'issued_at' => 'datetime'];
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
