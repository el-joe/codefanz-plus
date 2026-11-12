<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSettingsRequest;
use App\Services\Ai\AiGateway;
use App\Services\AuditLogger;
use App\Services\Settings;
use Illuminate\Http\RedirectResponse;
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
        ]);
    }

    public function update(UpdateSettingsRequest $request, string $group, AuditLogger $audit): RedirectResponse
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

        if ($after !== []) {
            $audit->record(operation: "settings.{$group}.updated", user: $request->user(), before: $before, after: $after);
        }

        return redirect()->route('admin.settings.edit', $group)->with('toast', __('Settings saved.'));
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
