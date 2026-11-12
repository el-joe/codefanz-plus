<?php

namespace App\Services\Payments\Concerns;

use App\Exceptions\PaymentGatewayException;
use App\Services\Money;
use App\Services\Settings;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Shared plumbing for the regional gateway drivers: settings lookups, a JSON HTTP client with the payment
 * timeouts, and errors turned into short PaymentGatewayException messages (never secrets or response bodies).
 *
 * @property Settings $settings
 */
trait CallsGatewayApi
{
    protected function setting(string $key): ?string
    {
        $value = $this->settings->get('payments', $key);

        return is_scalar($value) && (string) $value !== '' ? trim((string) $value) : null;
    }

    protected function requireSetting(string $key): string
    {
        return $this->setting($key) ?? throw new PaymentGatewayException('Requires credentials');
    }

    protected function http(): PendingRequest
    {
        return Http::acceptJson()
            ->asJson()
            ->timeout((int) config('codefanz.payments.timeout_seconds', 20))
            ->connectTimeout((int) config('codefanz.payments.connect_timeout_seconds', 5));
    }

    /**
     * Run a request and throw on HTTP errors, keeping only the status and a short gateway error code.
     *
     * @param  Closure(): Response  $call
     *
     * @throws PaymentGatewayException
     */
    protected function call(Closure $call, ?string $errorPath = null): Response
    {
        try {
            return $call()->throw();
        } catch (RequestException $exception) {
            $code = $errorPath !== null ? $exception->response->json($errorPath) : null;

            throw new PaymentGatewayException('HTTP '.$exception->response->status().(is_scalar($code) && (string) $code !== '' ? ' '.Str::limit((string) $code, 60, '') : ''), previous: $exception);
        } catch (ConnectionException $exception) {
            throw new PaymentGatewayException('Connection failed or timed out', previous: $exception);
        }
    }

    /**
     * Decimal amount with exactly two places, as the regional gateways sign and expect it ("1500.00").
     */
    protected static function decimal(int $minor): string
    {
        return number_format($minor / 100, 2, '.', '');
    }

    /**
     * Gateway decimal ("1500", "1500.5", 1500.5) to minor units without float arithmetic on the stored value.
     */
    protected static function minorFromDecimal(mixed $amount): int
    {
        if (is_int($amount)) {
            return $amount * 100;
        }

        $text = is_float($amount) ? number_format($amount, 2, '.', '') : trim((string) $amount);

        return preg_match('/^\d+(\.\d+)?$/', $text) === 1 ? (int) Money::toMinor($text) : 0;
    }

    /**
     * @param  list<string>  $currencies
     */
    protected function supportsListedCurrency(string $currency, array $currencies): bool
    {
        $currency = mb_strtoupper($currency);

        return in_array($currency, $currencies, true) && in_array($currency, (array) config('codefanz.payments.currencies'), true);
    }
}
