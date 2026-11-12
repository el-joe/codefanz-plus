<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Invoice;
use App\Models\Language;
use App\Models\Payment;
use App\Services\Settings;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Printable invoice and receipt pages (print CSS, no PDF library). `?lang=ar|en` prints in another active
 * language than the reader's interface, e.g. the customer's preferred language.
 */
trait RendersBillingDocuments
{
    protected function invoiceDocument(Request $request, Invoice $invoice, string $backUrl): View
    {
        $this->useDocumentLocale($request);
        $invoice->loadMissing(['items', 'customer', 'project:id,ulid,name,reference', 'milestone:id,title', 'paymentLink:id,ulid,reference,status,expires_at']);

        return view('billing.invoice', ['invoice' => $invoice, 'seller' => $this->seller(), 'backUrl' => $backUrl]);
    }

    protected function receiptDocument(Request $request, Payment $payment, string $backUrl): View
    {
        abort_if($payment->receipt === null, 404);

        $this->useDocumentLocale($request);
        $payment->loadMissing(['project:id,ulid,name,reference', 'milestone:id,title', 'link:id,ulid,reference,description']);

        return view('billing.receipt', ['payment' => $payment, 'receipt' => $payment->receipt, 'seller' => $this->seller(), 'backUrl' => $backUrl]);
    }

    private function useDocumentLocale(Request $request): void
    {
        $locale = $request->query('lang');

        if (is_string($locale) && Language::active()->has($locale)) {
            app()->setLocale($locale);
        }
    }

    /**
     * @return array{name: string, address: ?string, email: ?string, phone: ?string}
     */
    private function seller(): array
    {
        $settings = app(Settings::class);
        $locale = app()->getLocale();
        $name = $settings->get('general', 'company_name') ?? [];
        $address = $settings->get('contact', 'address') ?? [];

        return [
            'name' => (is_array($name) ? ($name[$locale] ?? $name[Language::defaultCode()] ?? null) : null) ?: 'CodeFanz',
            'address' => is_array($address) ? ($address[$locale] ?? $address[Language::defaultCode()] ?? null) : null,
            'email' => $settings->get('contact', 'email'),
            'phone' => $settings->get('contact', 'phone'),
        ];
    }
}
