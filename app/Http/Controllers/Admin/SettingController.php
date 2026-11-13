<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\TestsGatewayConnection;
use App\Exceptions\PaymentGatewayException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSettingsRequest;
use App\Services\Ai\AiGateway;
use App\Services\AuditLogger;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\Radar\RadarPlatforms;
use App\Services\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Business settings grouped by tab. Secrets are write-only: the UI only ever sees a masked value.
 */
class SettingController extends Controller
{
    public function __construct(private Settings $settings) {}

    public function edit(string $group): View
    {
        $fields = config('codefanz.settings.'.$group) ?? abort(404);

        $values = [];
        $masked = [];

        foreach ($fields as $key => $field) {
            if ($field['type'] === 'secret') {
                $masked[$key] = $this->settings->masked($group, $key);
            } else {
                $values[$key] = $this->settings->get($group, $key);
            }
        }

        return view('admin.settings.edit', [
            'groups' => array_keys(config('codefanz.settings')),
            'group' => $group,
            'fields' => $fields,
            'values' => $values,
            'masked' => $masked,
            'integrations' => $group === 'integrations' ? $this->integrationStatuses() : [],
            'paymentGateways' => $group === 'payments' ? app(PaymentGatewayManager::class)->statuses() : [],
        ]);
    }

    public function update(UpdateSettingsRequest $request, string $group, AuditLogger $audit, PaymentGatewayManager $gateways): RedirectResponse
    {
        $input = $request->validated('settings') ?? [];
        $clear = $request->validated('clear') ?? [];
        $before = [];
        $after = [];

        foreach ($request->fields() as $key => $field) {
            $isSecret = $field['type'] === 'secret';
            $value = $input[$key] ?? null;

            if ($field['type'] === 'translatable') {
                $value = array_filter((array) $value, fn ($text): bool => filled($text));
            }

            if ($isSecret && in_array($key, $clear, true)) {
                if ($this->settings->has($group, $key)) {
                    $this->settings->forget($group, $key);
                    [$before[$key], $after[$key]] = ['[secret]', null];
                }

                continue;
            }

            // A blank secret keeps the stored value; secrets are never sent back to the browser.
            if ($isSecret && blank($value)) {
                continue;
            }

            $current = $this->settings->get($group, $key);

            if ($isSecret || $current !== (blank($value) ? null : $value)) {
                blank($value)
                    ? $this->settings->forget($group, $key)
                    : $this->settings->set($group, $key, $value, encrypted: $isSecret, user: $request->user());

                $before[$key] = $isSecret ? ($current === null ? null : '[secret]') : $current;
                $after[$key] = $isSecret ? '[secret updated]' : (blank($value) ? null : $value);
            }
        }

        // New payment credentials have not been tested: drop the "Connected" verification of the gateways concerned.
        if ($group === 'payments' && $after !== []) {
            $cleared = $gateways->forgetVerificationsFor(array_keys($after));

            if ($cleared !== []) {
                $after['connection_verification_cleared'] = $cleared;
            }
        }

        if ($group === 'radar' && $after !== []) {
            app(RadarPlatforms::class)->refreshStatuses();
        }

        if ($after !== []) {
            $audit->record(operation: "settings.{$group}.updated", user: $request->user(), before: $before, after: $after);
        }

        return redirect()->route('admin.settings.edit', $group)->with('toast', __('Settings saved.'));
    }

    /**
     * "Test connection": a harmless authenticated call to the gateway. Success records the verification date shown
     * on the status card; failure is reported with a short, secret-free reason. Both are audited.
     */
    public function testGateway(Request $request, string $gateway, PaymentGatewayManager $gateways, AuditLogger $audit): RedirectResponse
    {
        abort_unless($gateways->has($gateway), 404);

        $driver = $gateways->driver($gateway);

        if (! $driver instanceof TestsGatewayConnection) {
            return back()->with('toast', __(':gateway has no connection test.', ['gateway' => $gateways->label($gateway)]));
        }

        if (! $driver->isConfigured()) {
            return back()->with('toast', __('Save every :gateway credential before testing the connection.', ['gateway' => $gateways->label($gateway)]));
        }

        try {
            $driver->testConnection();
        } catch (PaymentGatewayException $exception) {
            $audit->record(operation: 'settings.payments.connection_tested', user: $request->user(), after: ['gateway' => $gateway, 'mode' => $driver->mode()->value, 'error' => $exception->getMessage()], result: 'failure');

            return back()->with('toast', __(':gateway connection failed: :error', ['gateway' => $gateways->label($gateway), 'error' => $exception->getMessage()]));
        }

        $gateways->recordVerification($gateway);
        $audit->record(operation: 'settings.payments.connection_tested', user: $request->user(), after: ['gateway' => $gateway, 'mode' => $driver->mode()->value]);

        return back()->with('toast', __(':gateway connection verified.', ['gateway' => $gateways->label($gateway)]));
    }

    /**
     * @return array<string, array{label: string, configured: bool, partial: bool, selected: bool}>
     */
    private function integrationStatuses(): array
    {
        $selectedProvider = app(AiGateway::class)->providerName()->value;

        return collect(config('codefanz.integrations'))->map(function (array $integration) use ($selectedProvider): array {
            $saved = collect($integration['keys'])->filter(fn (string $key): bool => $this->settings->has('integrations', $key))->count();

            return [
                'label' => $integration['label'],
                'configured' => $saved === count($integration['keys']),
                'partial' => $saved > 0 && $saved < count($integration['keys']),
                'selected' => ($integration['ai_provider'] ?? null) === $selectedProvider,
            ];
        })->all();
    }
}
