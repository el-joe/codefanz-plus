<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Billing\CreateInvoice;
use App\Actions\Billing\IssueInvoice;
use App\Actions\Billing\UpdateDraftInvoice;
use App\Actions\Billing\VoidInvoice;
use App\Enums\InvoiceStatus;
use App\Http\Controllers\Concerns\RendersBillingDocuments;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateInvoiceRequest;
use App\Models\Invoice;
use App\Models\Milestone;
use App\Models\PaymentLink;
use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Invoices (A05 tab): list, draft from a milestone or a payment link, edit the draft, issue (numbered, customer
 * snapshot frozen), void, and the bilingual printable view.
 */
class InvoiceController extends Controller
{
    use RendersBillingDocuments;

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Invoice::class);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::enum(InvoiceStatus::class)],
        ]);

        $invoices = Invoice::query()
            ->with(['customer:id,ulid,company_name', 'project:id,ulid,name,reference', 'milestone:id,title'])
            ->when($filters['q'] ?? null, function (Builder $query, string $term): void {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $query->where(fn (Builder $query) => $query
                    ->where('number', 'like', $like)
                    ->orWhereHas('customer', fn (Builder $customer) => $customer->where('company_name', 'like', $like)));
            })
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->latest()
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.payments.invoices', ['invoices' => $invoices, 'filters' => $filters]);
    }

    public function show(Invoice $invoice): View
    {
        Gate::authorize('view', $invoice);

        $invoice->load(['items', 'customer', 'project:id,ulid,name,reference', 'milestone:id,title', 'paymentLink:id,ulid,reference,status,expires_at', 'creator:id,name']);

        return view('admin.payments.invoice', ['invoice' => $invoice]);
    }

    public function storeFromMilestone(Request $request, Project $project, Milestone $milestone, CreateInvoice $create): RedirectResponse
    {
        Gate::authorize('create', Invoice::class);
        Gate::authorize('view', $project);

        $invoice = $create->fromMilestone($milestone, $request->user());

        return redirect()->route('admin.invoices.show', $invoice)->with('toast', __('Draft invoice created.'));
    }

    public function storeFromLink(Request $request, PaymentLink $paymentLink, CreateInvoice $create): RedirectResponse
    {
        Gate::authorize('create', Invoice::class);

        $invoice = $create->fromPaymentLink($paymentLink, $request->user());

        return redirect()->route('admin.invoices.show', $invoice)->with('toast', __('Draft invoice created.'));
    }

    public function update(UpdateInvoiceRequest $request, Invoice $invoice, UpdateDraftInvoice $update): RedirectResponse
    {
        $update->handle($invoice, $request->invoiceData(), $request->user());

        return redirect()->route('admin.invoices.show', $invoice)->with('toast', __('Draft saved.'));
    }

    public function issue(Request $request, Invoice $invoice, IssueInvoice $issue): RedirectResponse
    {
        Gate::authorize('update', $invoice);

        $invoice = $issue->handle($invoice, $request->user());

        return redirect()->route('admin.invoices.show', $invoice)->with('toast', __('Invoice :number issued.', ['number' => $invoice->number]));
    }

    public function void(Request $request, Invoice $invoice, VoidInvoice $void): RedirectResponse
    {
        Gate::authorize('update', $invoice);

        $validated = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        $void->handle($invoice, $validated['reason'], $request->user());

        return redirect()->route('admin.invoices.show', $invoice)->with('toast', __('Invoice voided.'));
    }

    public function print(Request $request, Invoice $invoice): View
    {
        Gate::authorize('view', $invoice);

        return $this->invoiceDocument($request, $invoice, route('admin.invoices.show', $invoice));
    }
}
