<?php

namespace App\Http\Controllers;

use App\Actions\Payments\HandleGatewayWebhook;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POST /webhooks/payments/{gateway}. Outside the web middleware group (no session, no CSRF): authenticity comes
 * from the gateway's signature, checked in the driver.
 */
class PaymentWebhookController extends Controller
{
    public function __invoke(Request $request, string $gateway, PaymentGatewayManager $gateways, HandleGatewayWebhook $handle): JsonResponse
    {
        abort_unless($gateways->has($gateway), 404);

        $outcome = $handle->handle($gateway, $request);

        return response()->json(['status' => $outcome], match ($outcome) {
            HandleGatewayWebhook::INVALID => 400,
            HandleGatewayWebhook::RETRY => 503,
            default => 200,
        });
    }
}
