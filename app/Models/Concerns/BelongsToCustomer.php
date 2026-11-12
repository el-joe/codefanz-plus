<?php

namespace App\Models\Concerns;

use App\Models\Customer;
use App\Services\CurrentCustomer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * For client-owned records (projects, invoices, documents, tickets...) that carry a `customer_id` column.
 *
 * Portal code must always query through `forCurrentCustomer()`; the scope is explicit (not global) so admin
 * screens keep seeing every customer's records.
 *
 * @mixin Model
 */
trait BelongsToCustomer
{
    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeForCustomer(Builder $query, Customer|int $customer): void
    {
        $query->where($this->qualifyColumn('customer_id'), $customer instanceof Customer ? $customer->getKey() : $customer);
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeForCurrentCustomer(Builder $query): void
    {
        $this->scopeForCustomer($query, app(CurrentCustomer::class)->id());
    }

    public function belongsToCustomer(Customer|int $customer): bool
    {
        return (int) $this->getAttribute('customer_id') === ($customer instanceof Customer ? $customer->getKey() : $customer);
    }
}
