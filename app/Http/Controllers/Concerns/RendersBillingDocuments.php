<?php

namespace App\Http\Controllers\Concerns;

use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\Language;
use App\Models\Payment;
use App\Services\SellerDetails;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Printable invoice, receipt and credit note pages (print CSS, no PDF library). `?lang=ar|en` prints in another active
 * language than the reader's interface, e.g. the customer's preferred language.
 */
trait RendersBillingDocuments
{
    protected function invoiceDocument(Request $request, Invoice $invoice, string $backUrl): View
    {
        $this->useDocumentLocale($request);
        $invoice->loadMissing(['items', 'customer', 'project:id,ulid,name,reference', 'milestone:id,title', 'paymentLink:id,ulid,reference,status,expires_at']);

        return view('billing.invoice', ['invoice' => $invoice, 'seller' => $this->seller($invoice->seller_snapshot), 'backUrl' => $backUrl]);
    }

    protected function receiptDocument(Request $request, Payment $payment, string $backUrl): View
    {
        abort_if($payment->receipt === null, 404);

        $this->useDocumentLocale($request);
        $payment->loadMissing(['project:id,ulid,name,reference', 'milestone:id,title', 'link:id,ulid,reference,description']);

        return view('billing.receipt', ['payment' => $payment, 'receipt' => $payment->receipt, 'seller' => $this->seller($payment->receipt->seller_snapshot), 'backUrl' => $backUrl]);
    }

    private function useDocumentLocale(Request $request): void
    {
        $locale = $request->query('lang');

        if (is_string($locale) && Language::active()->has($locale)) {
            app()->setLocale($locale);
        }
    }

    protected function creditNoteDocument(Request $request, CreditNote $creditNote, string $backUrl): View
    {
        $this->useDocumentLocale($request);
        $creditNote->loadMissing(['payment:id,ulid,reference,currency,amount_minor', 'refund:id,ulid,reference']);

        return view('billing.credit-note', ['creditNote' => $creditNote, 'seller' => $this->seller($creditNote->seller_snapshot), 'backUrl' => $backUrl]);
    }

    /**
     * Seller block in the document language: the snapshot frozen at issue, or live settings for drafts and
     * documents issued before snapshots existed.
     *
     * @param  array<string, mixed>|null  $snapshot
     * @return array{name: string, address: ?string, email: ?string, phone: ?string}
     */
    private function seller(?array $snapshot): array
    {
        return app(SellerDetails::class)->forLocale($snapshot);
    }
}
