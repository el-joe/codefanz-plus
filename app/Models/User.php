<?php

namespace App\Models;

use App\Enums\CustomerStatus;
use App\Enums\Permission;
use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'locale', 'timezone', 'is_active'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable implements HasLocalePreference
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
            'author_title' => 'array',
            'author_bio' => 'array',
        ];
    }

    /**
     * Mail and notifications are sent in the user's own language.
     */
    public function preferredLocale(): ?string
    {
        return $this->locale;
    }

    /**
     * Active users who may own leads: their roles grant leads.manage (Super Admin's role grants everything).
     *
     * @param  Builder<User>  $query
     */
    public function scopeLeadManagers(Builder $query): void
    {
        $query->where('is_active', true)
            ->whereHas('roles.permissions', fn (Builder $permissions) => $permissions->where('name', Permission::LeadsManage->value));
    }

    /**
     * Customer accounts this user is (or was) linked to as a portal user, with the membership on the pivot.
     *
     * @return BelongsToMany<Customer, $this, CustomerUser>
     */
    public function customers(): BelongsToMany
    {
        return $this->belongsToMany(Customer::class)
            ->using(CustomerUser::class)
            ->withPivot(['id', 'role', 'invited_at', 'revoked_at'])
            ->withTimestamps();
    }

    /**
     * Customers this user may open in the portal right now: membership not revoked and customer active.
     *
     * @return BelongsToMany<Customer, $this, CustomerUser>
     */
    public function portalCustomers(): BelongsToMany
    {
        return $this->customers()->wherePivotNull('revoked_at')->where('customers.status', CustomerStatus::Active->value);
    }

    /**
     * Delivery projects this staff member works on.
     *
     * @return BelongsToMany<Project, $this>
     */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_members')->withPivot('role')->withTimestamps();
    }

    /**
     * Active staff who can be put on a project team or assigned a ticket.
     *
     * @param  Builder<User>  $query
     */
    public function scopeHoldingPermission(Builder $query, Permission $permission): void
    {
        $query->where('is_active', true)
            ->whereHas('roles.permissions', fn (Builder $permissions) => $permissions->where('name', $permission->value));
    }

    public function isStaff(): bool
    {
        return $this->roles->contains(fn ($role): bool => $role->name !== Role::Client->value);
    }

    public function requiresTwoFactor(): bool
    {
        return $this->roles->contains(
            fn ($role): bool => Role::tryFrom($role->name)?->requiresTwoFactor() ?? false,
        );
    }

    public function hasConfirmedTwoFactor(): bool
    {
        return $this->two_factor_confirmed_at !== null;
    }

    public function initials(): string
    {
        return collect(explode(' ', trim($this->name)))
            ->filter()
            ->take(2)
            ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');
    }

    /**
     * Public author job title on blog articles (current locale, falling back to English).
     */
    public function authorTitle(?string $locale = null): ?string
    {
        $titles = $this->author_title ?? [];

        return ($titles[$locale ?? app()->getLocale()] ?? null) ?: (($titles['en'] ?? null) ?: null);
    }

    /**
     * Public author bio on blog articles (current locale, falling back to English).
     */
    public function authorBio(?string $locale = null): ?string
    {
        $bios = $this->author_bio ?? [];

        return ($bios[$locale ?? app()->getLocale()] ?? null) ?: (($bios['en'] ?? null) ?: null);
    }
}
