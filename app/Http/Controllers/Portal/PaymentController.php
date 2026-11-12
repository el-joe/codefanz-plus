<?php

namespace App\Http\Controllers\Portal;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Concerns\RendersBillingDocuments;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Payment history (P09) for the current customer, failed attempts shown honestly, and receipts for verified
 * payments. Other customers' payments and receipts are a 404.
 */
class PaymentController extends Controller
{
    use RendersBillingDocuments;

    public function index(): View
    {
        $payments = Payment::query()
            ->forCurrentCustomer()
            ->whereNotNull('gateway_payment_id')
            ->with(['project:id,ulid,name', 'milestone:id,title', 'receipt:id,payment_id,number'])
            ->latest()
            ->latest('id')
            ->get();

        $collected = $payments->filter(fn (Payment $payment): bool => $payment->status->isCollected());

        return view('portal.payments.index', [
            'payments' => $payments,
            'totals' => $collected->groupBy('currency')->map(fn ($rows): int => (int) $rows->sum(fn (Payment $payment): int => $payment->collectedMinor())),
            'refunds' => $payments->groupBy('currency')->map(fn ($rows): int => (int) $rows->sum('refunded_minor'))->filter(),
            'lastPaidAt' => $collected->max('paid_at'),
        ]);
    }

    public function receipt(Request $request, string $payment): View
    {
        $payment = Payment::query()
            ->forCurrentCustomer()
            ->whereIn('status', [...PaymentStatus::collected(), PaymentStatus::Refunded])
            ->where('ulid', $payment)
            ->with('receipt')
            ->firstOrFail();

        return $this->receiptDocument($request, $payment, route('portal.payments.index'));
    }
}
