<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Refunds\CompleteRefund;
use App\Actions\Refunds\DecideRefund;
use App\Actions\Refunds\RequestRefund;
use App\Enums\RefundStatus;
use App\Http\Controllers\Concerns\RendersBillingDocuments;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CompleteManualRefundRequest;
use App\Http\Requests\Admin\DecideRefundRequest;
use App\Http\Requests\Admin\StoreRefundRequest;
use App\Models\CreditNote;
use App\Models\Payment;
use App\Models\Refund;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Refunds tab of Payments (A05 "refund awaiting finance approval"): requests, four-eyes decisions, manual
 * completion and credit notes.
 */
class RefundController extends Controller
{
    use RendersBillingDocuments;

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Refund::class);

        $filters = $request->validate(['status' => ['nullable', Rule::enum(RefundStatus::class)]]);

        $refunds = Refund::query()
            ->with(['payment:id,ulid,reference,amount_minor,currency', 'customer:id,ulid,company_name', 'requester:id,name', 'decider:id,name', 'creditNote:id,ulid,refund_id,number'])
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->orderByRaw('CASE WHEN status IN (?, ?) THEN 0 ELSE 1 END', [RefundStatus::Requested->value, RefundStatus::ManualRequired->value])
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.payments.refunds', [
            'refunds' => $refunds,
            'filters' => $filters,
            'awaiting' => Refund::query()->where('status', RefundStatus::Requested)->count(),
        ]);
    }

    public function store(StoreRefundRequest $request, Payment $payment, RequestRefund $requestRefund): RedirectResponse
    {
        $refund = $requestRefund->handle($payment, $request->user(), $request->amountMinor(), $request->validated('reason'));

        return redirect()->route('admin.payments.show', $payment)->with('toast', __('Refund :reference requested. A finance approver has been notified.', ['reference' => $refund->reference]));
    }

    public function approve(DecideRefundRequest $request, Refund $refund, DecideRefund $decideRefund): RedirectResponse
    {
        $refund = $decideRefund->approve($refund, $request->user(), $request->validated('note'));

        return back()->with('toast', match ($refund->status) {
            RefundStatus::Succeeded => __('Refund :reference completed. Credit note issued.', ['reference' => $refund->reference]),
            RefundStatus::ManualRequired => __('Refund :reference approved. The gateway cannot refund this payment: complete it manually and record the reference.', ['reference' => $refund->reference]),
            RefundStatus::Failed => __('Refund :reference approved but the gateway refused it (:reason).', ['reference' => $refund->reference, 'reason' => $refund->failure_reason]),
            default => __('Refund :reference approved and sent to the gateway.', ['reference' => $refund->reference]),
        });
    }

    public function reject(DecideRefundRequest $request, Refund $refund, DecideRefund $decideRefund): RedirectResponse
    {
        $decideRefund->reject($refund, $request->user(), (string) $request->validated('note'));

        return back()->with('toast', __('Refund :reference rejected.', ['reference' => $refund->reference]));
    }

    public function complete(CompleteManualRefundRequest $request, Refund $refund, CompleteRefund $completeRefund): RedirectResponse
    {
        $completeRefund->handle($refund, $request->user(), manualReference: $request->validated('external_reference'));

        return back()->with('toast', __('Manual refund :reference recorded. Credit note issued.', ['reference' => $refund->reference]));
    }

    public function creditNote(Request $request, CreditNote $creditNote): View
    {
        Gate::authorize('viewAny', Refund::class);

        return $this->creditNoteDocument($request, $creditNote, route('admin.refunds.index'));
    }
}
