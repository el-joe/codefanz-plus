<?php

namespace App\Http\Controllers\Portal;

use App\Enums\InvoiceStatus;
use App\Http\Controllers\Concerns\RendersBillingDocuments;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Invoices (P08): the current customer's issued, paid and void invoices (never drafts), with Pay now when an
 * open payment link is attached, and the printable invoice. Other customers' invoices are a 404.
 */
class InvoiceController extends Controller
{
    use RendersBillingDocuments;

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', 'in:unpaid,paid,overdue'],
            'q' => ['nullable', 'string', 'max:60'],
        ]);

        $base = Invoice::query()->forCurrentCustomer()->visibleToClient();

        $invoices = (clone $base)
            ->with(['project:id,ulid,name', 'milestone:id,title', 'paymentLink:id,ulid,status,expires_at'])
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => match ($status) {
                'unpaid' => $query->where('status', InvoiceStatus::Issued),
                'paid' => $query->where('status', InvoiceStatus::Paid),
                'overdue' => $query->where('status', InvoiceStatus::Issued)->whereDate('due_at', '<', today()),
            })
            ->when($filters['q'] ?? null, fn (Builder $query, string $term) => $query->where('number', 'like', '%'.addcslashes($term, '%_\\').'%'))
            ->latest('issued_at')
            ->latest('id')
            ->get();

        return view('portal.invoices.index', [
            'invoices' => $invoices,
            'filters' => $filters,
            'unpaidCount' => (clone $base)->where('status', InvoiceStatus::Issued)->count(),
        ]);
    }

    public function print(Request $request, string $invoice): View
    {
        $invoice = Invoice::query()->forCurrentCustomer()->visibleToClient()->where('ulid', $invoice)->firstOrFail();

        return $this->invoiceDocument($request, $invoice, route('portal.invoices.index'));
    }
}
