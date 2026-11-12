<?php

namespace App\Models;

use App\Enums\CustomerStatus;
use App\Enums\CustomerType;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A customer account (company or individual). Admin and portal URLs use the ulid; people see the reference.
 * Portal users belong to customers through customer_user, so one company can have several logins.
 */
#[Fillable([
    'type', 'status', 'company_name', 'contact_name', 'email', 'phone', 'country', 'tax_id',
    'billing_address_line1', 'billing_address_line2', 'billing_city', 'billing_state', 'billing_postal_code',
    'preferred_locale', 'notes', 'created_from_lead_id', 'created_by', 'updated_by',
])]
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    /**
     * Mirror database defaults so a freshly created instance reports the correct values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'type' => 'company',
        'status' => 'active',
        'preferred_locale' => 'en',
    ];

    protected static function booted(): void
    {
        static::created(function (Customer $customer): void {
            $customer->forceFill(['reference' => sprintf('CU-%d-%05d', $customer->created_at?->year ?? now()->year, $customer->getKey())])->saveQuietly();
        });
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
            'type' => CustomerType::class,
            'status' => CustomerStatus::class,
        ];
    }

    /**
     * @param  Builder<Customer>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', CustomerStatus::Active);
    }

    public function isActive(): bool
    {
        return $this->status === CustomerStatus::Active;
    }

    /**
     * Every portal user ever linked, including revoked memberships.
     *
     * @return BelongsToMany<User, $this, CustomerUser>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(CustomerUser::class)
            ->withPivot(['id', 'role', 'invited_at', 'revoked_at'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<Project, $this>
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /**
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'created_from_lead_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Billing address lines in display order, blank parts skipped.
     *
     * @return list<string>
     */
    public function billingAddressLines(): array
    {
        return array_values(array_filter([
            $this->billing_address_line1,
            $this->billing_address_line2,
            trim(implode(' ', array_filter([$this->billing_city, $this->billing_state, $this->billing_postal_code]))),
        ], fn (?string $line): bool => filled($line)));
    }
}
