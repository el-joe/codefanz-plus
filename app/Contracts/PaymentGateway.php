<?php

namespace App\Contracts;

use App\Enums\GatewayMode;
use App\Exceptions\InvalidWebhookSignature;
use App\Exceptions\PaymentGatewayException;
use App\Models\Payment;
use App\Models\PaymentLink;
use App\Services\Payments\CheckoutSession;
use App\Services\Payments\GatewayRefund;
use App\Services\Payments\GatewayVerification;
use App\Services\Payments\GatewayWebhookEvent;
use Illuminate\Http\Request;

/**
 * A payment provider adapter (Stripe now; Paymob, PayTabs and Fawry next). Drivers only talk to the provider:
 * they never write to the ledger. App\Actions\Payments\VerifyPayment decides what a verification means for the
 * payment, and App\Actions\Payments\HandleGatewayWebhook records and deduplicates webhooks.
 *
 * Register a driver in config('codefanz.payments.gateways') with its credential keys; the
 * App\Services\Payments\PaymentGatewayManager resolves it by key.
 */
interface PaymentGateway
{
    /**
     * Key used in config, routes (/webhooks/payments/{key}) and payments.gateway.
     */
    public function key(): string;

    /**
     * True once every credential the driver needs is saved in Settings.
     */
    public function isConfigured(): bool;

    public function mode(): GatewayMode;

    public function supportsCurrency(string $currency): bool;

    /**
     * Create a hosted checkout for the (already stored, pending) payment.
     *
     * @throws PaymentGatewayException
     */
    public function createCheckout(PaymentLink $link, Payment $payment): CheckoutSession;

    /**
     * Ask the provider's API for the payment's real status. Never derived from browser input.
     *
     * @throws PaymentGatewayException
     */
    public function verifyPayment(Payment $payment): GatewayVerification;

    /**
     * Authenticate a webhook delivery (signature, timestamp tolerance) and extract what it refers to.
     *
     * @throws InvalidWebhookSignature
     */
    public function parseWebhook(Request $request): GatewayWebhookEvent;

    /**
     * Refund part or all of a collected payment (used by the refund approval workflow).
     *
     * @throws PaymentGatewayException
     */
    public function refund(Payment $payment, int $amountMinor): GatewayRefund;
}
