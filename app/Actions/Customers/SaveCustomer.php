<?php

namespace App\Actions\Customers;

use App\Enums\LeadActivityType;
use App\Enums\LeadStatus;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates or updates a customer account. Creating from a won lead links the lead and notes it on its timeline.
 */
class SaveCustomer
{
    /**
     * Columns recorded in the audit trail.
     *
     * @var list<string>
     */
    private const AUDITED = [
        'type', 'status', 'company_name', 'contact_name', 'email', 'phone', 'country', 'tax_id',
        'billing_address_line1', 'billing_address_line2', 'billing_city', 'billing_state', 'billing_postal_code',
        'preferred_locale', 'created_from_lead_id',
    ];

    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $data  Validated SaveCustomerRequest data (without the lead key).
     *
     * @throws ValidationException
     */
    public function handle(?Customer $customer, array $data, User $actor, ?Lead $lead = null): Customer
    {
        $isNew = $customer === null;

        return DB::transaction(function () use ($customer, $data, $actor, $lead, $isNew): Customer {
            if ($lead !== null) {
                $this->guardConvertible($lead);
            }

            $customer ??= new Customer(['created_by' => $actor->getKey()]);
            $before = $isNew ? null : $customer->only(self::AUDITED);

            $customer->fill([...$data, 'updated_by' => $actor->getKey()]);

            if ($isNew && $lead !== null) {
                $customer->created_from_lead_id = $lead->getKey();
            }

            $customer->save();

            if ($isNew && $lead !== null) {
                $lead->activities()->create([
                    'type' => LeadActivityType::System,
                    'user_id' => $actor->getKey(),
                    'meta' => ['event' => 'converted', 'customer' => $customer->ulid, 'reference' => $customer->reference],
                ]);
                $lead->touch();
            }

            $this->audit->record(
                operation: match (true) {
                    $isNew && $lead !== null => 'customer.created_from_lead',
                    $isNew => 'customer.created',
                    default => 'customer.updated',
                },
                auditable: $customer,
                user: $actor,
                before: $before,
                after: $customer->only(self::AUDITED),
            );

            return $customer;
        });
    }

    /**
     * @throws ValidationException
     */
    private function guardConvertible(Lead $lead): void
    {
        $lead = Lead::query()->lockForUpdate()->findOrFail($lead->getKey());

        if ($lead->status !== LeadStatus::Won) {
            throw ValidationException::withMessages(['lead' => __('Only won leads can be converted into customers.')]);
        }

        if (Customer::withTrashed()->where('created_from_lead_id', $lead->getKey())->exists()) {
            throw ValidationException::withMessages(['lead' => __('This lead has already been converted into a customer.')]);
        }
    }
}
