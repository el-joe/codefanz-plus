<?php

namespace App\Models;

use App\Enums\PaymentLinkStatus;
use App\Models\Concerns\BelongsToCustomer;
use App\Services\Money;
use Database\Factories\PaymentLinkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * A payment request sent to a customer. The public URL is /pay/{ulid}; visitors can never change the amount.
 * Created by App\Actions\Payments\CreatePaymentLink (milestone links are capped at the milestone's remaining
 * balance) and never deleted, so the ledger keeps every request ever sent.
 */
#[Fillable([
    'customer_id', 'project_id', 'milestone_id', 'amount_minor', 'currency', 'description', 'expires_at',
    'single_use', 'gateways', 'status', 'created_by',
])]
class PaymentLink extends Model
{
    /** @use HasFactory<PaymentLinkFactory> */
    use BelongsToCustomer, HasFactory, HasUlids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
        'single_use' => true,
        'uses' => 0,
    ];

    protected static function booted(): void
    {
        static::created(function (PaymentLink $link): void {
            $link->forceFill(['reference' => sprintf('PL-%d-%05d', $link->created_at?->year ?? now()->year, $link->getKey())])->saveQuietly();
        });

        static::deleting(fn () => throw new LogicException('Payment links are part of the ledger and cannot be deleted.'));
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
            'status' => PaymentLinkStatus::class,
            'amount_minor' => 'integer',
            'single_use' => 'boolean',
            'gateways' => 'array',
            'uses' => 'integer',
            'expires_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * Active links that have not reached their expiry yet.
     *
     * @param  Builder<PaymentLink>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->where('status', PaymentLinkStatus::Active)
            ->where(fn (Builder $query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()));
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
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The stored status, with an active link past its expiry reported as expired (before the hourly sweep).
     */
    public function effectiveStatus(): PaymentLinkStatus
    {
        if ($this->status === PaymentLinkStatus::Active && $this->expires_at !== null && $this->expires_at->isPast()) {
            return PaymentLinkStatus::Expired;
        }

        return $this->status;
    }

    public function isPayable(): bool
    {
        return $this->effectiveStatus() === PaymentLinkStatus::Active;
    }

    /**
     * @return list<string>
     */
    public function gatewayKeys(): array
    {
        return array_values(array_map('strval', (array) $this->gateways));
    }

    public function formattedAmount(): string
    {
        return Money::localized($this->amount_minor, $this->currency);
    }

    public function publicUrl(): string
    {
        return route('pay.show', $this);
    }
}
