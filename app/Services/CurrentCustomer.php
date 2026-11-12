<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Contracts\Session\Session;
use Illuminate\Database\Eloquent\Collection;
use RuntimeException;

/**
 * The customer account the signed-in portal user is acting for during this request.
 *
 * Resolved by the `portal` middleware (EnsurePortalAccess) from the session and re-validated against the
 * user's memberships on every request. Portal modules scope their queries with it, e.g.
 * `Project::query()->forCurrentCustomer()` (see App\Models\Concerns\BelongsToCustomer).
 */
class CurrentCustomer
{
    public const SESSION_KEY = 'portal.customer';

    private ?Customer $customer = null;

    /**
     * @var Collection<int, Customer>|null
     */
    private ?Collection $available = null;

    /**
     * Pick the customer for this request: the one stored in the session if the user may still open it,
     * otherwise the first customer they can access. Returns null when the user has no portal access.
     */
    public function resolve(User $user, Session $session): ?Customer
    {
        $this->available = $user->portalCustomers()->orderBy('company_name')->get();

        $stored = $session->get(self::SESSION_KEY);
        $customer = $this->available->firstWhere('ulid', $stored) ?? $this->available->first();

        if ($customer === null) {
            $session->forget(self::SESSION_KEY);
            $this->customer = null;

            return null;
        }

        $session->put(self::SESSION_KEY, $customer->ulid);
        $this->customer = $customer;

        return $customer;
    }

    /**
     * Switch to another customer the user may open. Foreign or revoked customers are rejected.
     */
    public function switchTo(User $user, string $ulid, Session $session): ?Customer
    {
        $customer = $user->portalCustomers()->where('customers.ulid', $ulid)->first();

        if ($customer !== null) {
            $session->put(self::SESSION_KEY, $customer->ulid);
            $this->customer = $customer;
        }

        return $customer;
    }

    public function has(): bool
    {
        return $this->customer !== null;
    }

    public function get(): Customer
    {
        return $this->customer ?? throw new RuntimeException('No current customer: the route is not behind the portal middleware.');
    }

    public function id(): int
    {
        return $this->get()->getKey();
    }

    /**
     * Customers the user can switch between (only meaningful after resolve()).
     *
     * @return Collection<int, Customer>
     */
    public function available(): Collection
    {
        return $this->available ?? new Collection;
    }
}
