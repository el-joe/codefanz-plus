<?php

namespace App\Http\Controllers;

use App\Actions\Payments\StartCheckout;
use App\Actions\Payments\VerifyPayment;
use App\Enums\PaymentEventSource;
use App\Enums\PaymentStatus;
use App\Exceptions\PaymentGatewayException;
use App\Http\Controllers\Concerns\RendersBillingDocuments;
use App\Models\Payment;
use App\Models\PaymentLink;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Public checkout (P03) at /pay/{ulid}: no sign-in, the unguessable ulid is the capability. The amount is fixed
 * by the link; the payer only picks a gateway. Returning from the gateway re-verifies with the gateway API —
 * the redirect itself never marks anything paid.
 */
class PayController extends Controller
{
    use RendersBillingDocuments;

    public function show(Request $request, PaymentLink $paymentLink, PaymentGatewayManager $gateways): View
    {
        $paymentLink->load(['customer:id,company_name', 'project:id,name,reference', 'milestone:id,title']);

        return view('pay.show', [
            'link' => $paymentLink,
            'status' => $paymentLink->effectiveStatus(),
            'gateways' => $paymentLink->isPayable() ? array_keys($gateways->availableFor($paymentLink)) : [],
            'billedTo' => self::mask($paymentLink->customer->company_name),
            'cancelled' => $request->boolean('cancelled'),
        ]);
    }

    public function checkout(Request $request, PaymentLink $paymentLink, StartCheckout $startCheckout): Response
    {
        $validated = $request->validate(['gateway' => ['required', 'string', 'max:30']]);

        [, $session] = $startCheckout->handle($paymentLink, $validated['gateway']);

        return redirect()->away((string) $session->redirectUrl);
    }

    /**
     * Back from the gateway: ask the gateway API for the real status. Without a confirmed verification the page
     * says the payment is being confirmed and nothing in the ledger changes.
     */
    public function return(PaymentLink $paymentLink, Payment $payment, VerifyPayment $verifyPayment): View
    {
        try {
            $payment = $verifyPayment->handle($payment, PaymentEventSource::ReturnUrl);
        } catch (PaymentGatewayException $exception) {
            Log::warning('Return-URL verification failed.', ['payment' => $payment->reference, 'error' => $exception->getMessage()]);
        }

        $payment->load(['receipt', 'milestone:id,title', 'project:id,name']);
        $paymentLink->load('customer:id,company_name');

        return view('pay.return', [
            'link' => $paymentLink,
            'payment' => $payment,
            'receiptUrl' => $payment->status === PaymentStatus::Paid && $payment->receipt
                ? URL::temporarySignedRoute('pay.receipt', now()->addHours((int) config('codefanz.payments.receipt_link_hours', 72)), ['paymentLink' => $paymentLink, 'payment' => $payment])
                : null,
        ]);
    }

    public function receipt(Request $request, PaymentLink $paymentLink, Payment $payment): View
    {
        $payment->load('receipt');

        return $this->receiptDocument($request, $payment, route('pay.show', $paymentLink));
    }

    /**
     * "ClinicPlus" → "Cl•••••us": enough for the payer to recognise the request, not a customer directory.
     */
    public static function mask(string $name): string
    {
        $length = mb_strlen($name);

        if ($length <= 4) {
            return mb_substr($name, 0, 1).str_repeat('•', max(1, $length - 1));
        }

        return mb_substr($name, 0, 2).str_repeat('•', min(8, $length - 4)).mb_substr($name, -2);
    }
}
