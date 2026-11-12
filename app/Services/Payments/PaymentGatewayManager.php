<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Contracts\TestsGatewayConnection;
use App\Enums\GatewayMode;
use App\Exceptions\UnknownPaymentGateway;
use App\Models\PaymentLink;
use App\Services\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Container\Container;

/**
 * Resolves payment gateway drivers by key from config('codefanz.payments.gateways'). Adding a provider is a new
 * driver class plus a config entry; nothing else in the payment flow changes.
 */
class PaymentGatewayManager
{
    /**
     * @var array<string, PaymentGateway>
     */
    private array $drivers = [];

    public function __construct(private Container $container, private Settings $settings) {}

    /**
     * Registered gateway keys, in config order.
     *
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys((array) config('codefanz.payments.gateways'));
    }

    public function has(string $key): bool
    {
        return in_array($key, $this->keys(), true);
    }

    public function driver(string $key): PaymentGateway
    {
        if (! $this->has($key)) {
            throw new UnknownPaymentGateway("Unknown payment gateway [{$key}].");
        }

        return $this->drivers[$key] ??= $this->container->make(
            (string) config("codefanz.payments.gateways.{$key}.driver"),
            ['key' => $key, 'config' => (array) config("codefanz.payments.gateways.{$key}")],
        );
    }

    /**
     * @return array<string, string>
     */
    public function labels(): array
    {
        return collect($this->keys())->mapWithKeys(fn (string $key): array => [$key => $this->label($key)])->all();
    }

    public function label(string $key): string
    {
        return (string) (config("codefanz.payments.gateways.{$key}.label") ?? $key);
    }

    /**
     * Gateways the payer can use for this link: allowed on the link, configured and supporting its currency.
     *
     * @return array<string, PaymentGateway>
     */
    public function availableFor(PaymentLink $link): array
    {
        $available = [];

        foreach ($link->gatewayKeys() as $key) {
            if (! $this->has($key)) {
                continue;
            }

            $driver = $this->driver($key);

            if ($driver->isConfigured() && $driver->supportsCurrency($link->currency)) {
                $available[$key] = $driver;
            }
        }

        return $available;
    }

    /**
     * Honest integration status per gateway for the settings and payment-link screens. "Connected" appears only
     * after the driver's connection test succeeded with the credentials saved now (any credential change clears
     * it); otherwise a gateway is at most "Sandbox" or "Live · not verified".
     *
     * @return array<string, array{label: string, status: string, configured: bool, mode: string, webhook_url: string, verified_at: ?CarbonImmutable, testable: bool, currencies: list<string>}>
     */
    public function statuses(): array
    {
        $statuses = [];

        foreach ($this->keys() as $key) {
            $driver = $this->driver($key);
            $credentials = (array) config("codefanz.payments.gateways.{$key}.credentials", []);
            $saved = collect($credentials)->filter(fn (string $credential): bool => $this->settings->has('payments', $credential))->count();
            $verifiedAt = $saved === count($credentials) ? $this->verifiedAt($key) : null;

            $status = match (true) {
                $saved === 0 => 'requires_credentials',
                $saved < count($credentials) => 'incomplete',
                $verifiedAt !== null => 'connected',
                $driver->mode() === GatewayMode::Live => 'live_unverified',
                default => 'sandbox',
            };

            $statuses[$key] = [
                'label' => $this->label($key),
                'status' => $status,
                'configured' => $driver->isConfigured(),
                'mode' => $driver->mode()->value,
                'webhook_url' => route('webhooks.payments', $key),
                'verified_at' => $verifiedAt,
                'testable' => $driver instanceof TestsGatewayConnection,
                'currencies' => array_values(array_filter((array) config('codefanz.payments.currencies'), fn (string $currency): bool => $driver->supportsCurrency($currency))),
            ];
        }

        return $statuses;
    }

    /**
     * Gateway keys that cannot take payments in this currency.
     *
     * @param  list<string>  $keys
     * @return list<string>
     */
    public function unsupportedFor(array $keys, string $currency): array
    {
        return array_values(array_filter($keys, fn (string $key): bool => $this->has($key) && ! $this->driver($key)->supportsCurrency($currency)));
    }

    public function verifiedAt(string $key): ?CarbonImmutable
    {
        $value = $this->settings->get('payments', $key.'_verified_at');

        return is_string($value) && $value !== '' ? CarbonImmutable::parse($value) : null;
    }

    public function recordVerification(string $key): void
    {
        $this->settings->set('payments', $key.'_verified_at', now()->toIso8601String());
    }

    /**
     * Clear the connection test of every gateway whose credentials or mode were among the changed setting keys.
     *
     * @param  list<string>  $changedKeys
     * @return list<string> the gateways whose verification was cleared
     */
    public function forgetVerificationsFor(array $changedKeys): array
    {
        $cleared = [];

        foreach ($this->keys() as $key) {
            $watched = [...(array) config("codefanz.payments.gateways.{$key}.credentials", []), (string) config("codefanz.payments.gateways.{$key}.mode_key"), $key.'_region'];

            if (array_intersect($watched, $changedKeys) !== [] && $this->settings->has('payments', $key.'_verified_at')) {
                $this->settings->forget('payments', $key.'_verified_at');
                $cleared[] = $key;
            }
        }

        return $cleared;
    }
}
