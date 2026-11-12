<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Customers\SaveCustomer;
use App\Enums\CustomerStatus;
use App\Enums\CustomerType;
use App\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveCustomerRequest;
use App\Models\Customer;
use App\Models\Lead;
use App\Services\AuditLogger;
use App\Services\Countries;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Customer accounts (A03 table pattern), detail page with portal users, create/edit and lead conversion.
 */
class CustomerController extends Controller
{
    public function index(Request $request, Countries $countries): View
    {
        Gate::authorize('viewAny', Customer::class);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::enum(CustomerStatus::class)],
            'type' => ['nullable', Rule::enum(CustomerType::class)],
        ]);

        $customers = Customer::query()
            ->withCount(['users as portal_users_count' => fn (Builder $query) => $query->whereNull('customer_user.revoked_at')])
            ->when($filters['q'] ?? null, function (Builder $query, string $term): void {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $query->where(fn (Builder $query) => $query
                    ->where('company_name', 'like', $like)
                    ->orWhere('contact_name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('reference', 'like', $like));
            })
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->where('type', $type))
            ->latest()
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.customers.index', [
            'customers' => $customers,
            'filters' => $filters,
            'countries' => $countries,
        ]);
    }

    /**
     * New customer form; `?lead=<ulid>` prefills it from a won lead for conversion.
     */
    public function create(Request $request, Countries $countries): View|RedirectResponse
    {
        Gate::authorize('create', Customer::class);

        $lead = null;
        $customer = new Customer(['preferred_locale' => app()->getLocale()]);

        if ($request->filled('lead')) {
            $lead = Lead::query()->with('customer:id,ulid,created_from_lead_id')->where('ulid', (string) $request->query('lead'))->firstOrFail();
            Gate::authorize('view', $lead);

            if ($lead->customer !== null) {
                return redirect()->route('admin.customers.show', $lead->customer)->with('toast', __('This lead has already been converted into a customer.'));
            }

            if ($lead->status !== LeadStatus::Won) {
                return redirect()->route('admin.leads.show', $lead)->with('toast', __('Only won leads can be converted into customers.'));
            }

            $customer->fill([
                'type' => filled($lead->company) ? CustomerType::Company : CustomerType::Individual,
                'company_name' => $lead->company ?: $lead->name,
                'contact_name' => $lead->name,
                'email' => $lead->email,
                'phone' => $lead->phone,
                'country' => $lead->country,
                'preferred_locale' => $lead->preferred_locale,
            ]);
        }

        return view('admin.customers.form', ['customer' => $customer, 'lead' => $lead, 'countries' => $countries->options()]);
    }

    public function store(SaveCustomerRequest $request, SaveCustomer $save): RedirectResponse
    {
        $lead = $request->lead();

        if ($lead !== null) {
            Gate::authorize('view', $lead);
        }

        $customer = $save->handle(null, $request->customerData(), $request->user(), $lead);

        return redirect()->route('admin.customers.show', $customer)
            ->with('toast', __('Customer :reference created.', ['reference' => $customer->reference]));
    }

    public function show(Customer $customer, Countries $countries): View
    {
        Gate::authorize('view', $customer);

        $customer->load([
            'lead:id,ulid,reference,name',
            'creator:id,name',
            'users' => fn ($query) => $query->orderBy('name'),
        ]);

        return view('admin.customers.show', [
            'customer' => $customer,
            'countryName' => $customer->country ? $countries->name($customer->country) : null,
        ]);
    }

    public function edit(Customer $customer, Countries $countries): View
    {
        Gate::authorize('update', $customer);

        return view('admin.customers.form', ['customer' => $customer, 'lead' => null, 'countries' => $countries->options()]);
    }

    public function update(SaveCustomerRequest $request, Customer $customer, SaveCustomer $save): RedirectResponse
    {
        $save->handle($customer, $request->customerData(), $request->user());

        return redirect()->route('admin.customers.show', $customer)->with('toast', __('Customer saved.'));
    }

    /**
     * Soft delete. Blocked while anyone can still sign in to the portal for this customer.
     */
    public function destroy(Request $request, Customer $customer, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('delete', $customer);

        if ($customer->users()->wherePivotNull('revoked_at')->exists()) {
            return back()->with('toast', __('Switch off portal access for every user before deleting this customer.'));
        }

        $customer->delete();
        $audit->record(operation: 'customer.deleted', auditable: $customer, user: $request->user());

        return redirect()->route('admin.customers.index')->with('toast', __('Customer deleted.'));
    }
}
