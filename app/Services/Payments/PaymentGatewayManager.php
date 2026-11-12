<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Enums\GatewayMode;
use App\Exceptions\UnknownPaymentGateway;
use App\Models\PaymentLink;
use App\Services\Settings;
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
     * Honest integration status per gateway for the settings and payment-link screens. A gateway is never
     * reported as verified live: there is no connection test yet, only saved credentials and the chosen mode.
     *
     * @return array<string, array{label: string, status: string, configured: bool, mode: string, webhook_url: string}>
     */
    public function statuses(): array
    {
        $statuses = [];

        foreach ($this->keys() as $key) {
            $driver = $this->driver($key);
            $credentials = (array) config("codefanz.payments.gateways.{$key}.credentials", []);
            $saved = collect($credentials)->filter(fn (string $credential): bool => $this->settings->has('payments', $credential))->count();

            $status = match (true) {
                $saved === 0 => 'requires_credentials',
                $saved < count($credentials) => 'incomplete',
                $driver->mode() === GatewayMode::Live => 'live_unverified',
                default => 'sandbox',
            };

            $statuses[$key] = [
                'label' => $this->label($key),
                'status' => $status,
                'configured' => $driver->isConfigured(),
                'mode' => $driver->mode()->value,
                'webhook_url' => route('webhooks.payments', $key),
            ];
        }

        return $statuses;
    }
}
