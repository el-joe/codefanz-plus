<?php

namespace App\Actions\Payments;

use App\Enums\PaymentEventSource;
use App\Enums\PaymentStatus;
use App\Exceptions\PaymentGatewayException;
use App\Models\Milestone;
use App\Models\Payment;
use App\Models\PaymentLink;
use App\Services\Payments\CheckoutSession;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * The payer picked a gateway on /pay/{link}: record a pending payment for the link's fixed amount and create
 * the gateway's hosted checkout. Nothing here can mark a payment paid.
 */
class StartCheckout
{
    public function __construct(private PaymentGatewayManager $gateways) {}

    /**
     * @return array{0: Payment, 1: CheckoutSession}
     *
     * @throws ValidationException
     */
    public function handle(PaymentLink $link, string $gatewayKey): array
    {
        $payment = DB::transaction(function () use ($link, $gatewayKey): Payment {
            $link = PaymentLink::query()->lockForUpdate()->findOrFail($link->getKey());

            if (! $link->isPayable()) {
                throw ValidationException::withMessages(['gateway' => __('This payment link can no longer be paid.')]);
            }

            if (! array_key_exists($gatewayKey, $this->gateways->availableFor($link))) {
                throw ValidationException::withMessages(['gateway' => __('Choose one of the available payment methods.')]);
            }

            if ($link->milestone_id !== null) {
                $milestone = Milestone::query()->lockForUpdate()->findOrFail($link->milestone_id);

                if ($milestone->paid_minor + $link->amount_minor > $milestone->amount_minor) {
                    throw ValidationException::withMessages(['gateway' => __('This milestone is already paid. Please contact CodeFanz.')]);
                }
            }

            $payment = Payment::create([
                'payment_link_id' => $link->getKey(),
                'customer_id' => $link->customer_id,
                'project_id' => $link->project_id,
                'milestone_id' => $link->milestone_id,
                'gateway' => $gatewayKey,
                'amount_minor' => $link->amount_minor,
                'currency' => $link->currency,
            ])->refresh();

            $payment->events()->create([
                'from_status' => null,
                'to_status' => PaymentStatus::Pending->value,
                'source' => PaymentEventSource::Checkout,
                'payload' => ['gateway' => $gatewayKey],
            ]);

            return $payment;
        });

        try {
            $session = $this->gateways->driver($gatewayKey)->createCheckout($link, $payment);
        } catch (PaymentGatewayException $exception) {
            DB::transaction(function () use ($payment, $exception): void {
                $locked = Payment::query()->lockForUpdate()->findOrFail($payment->getKey());
                $locked->transitionTo(PaymentStatus::Failed, PaymentEventSource::Checkout, ['failure_reason' => 'checkout_failed'], ['error' => $exception->getMessage()]);
            });

            Log::warning('Payment checkout could not be created.', ['payment' => $payment->reference, 'gateway' => $gatewayKey, 'error' => $exception->getMessage()]);

            throw ValidationException::withMessages(['gateway' => __('The payment provider is not reachable right now. Nothing was charged; please try again in a few minutes.')]);
        }

        $payment->forceFill([
            'gateway_payment_id' => $session->gatewayPaymentId,
            'metadata' => [...($payment->metadata ?? []), ...$session->metadata],
        ])->save();

        return [$payment, $session];
    }
}
