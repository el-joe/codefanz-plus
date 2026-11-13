<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Radar\ScoreRadarProject;
use App\Exceptions\RadarConnectorException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateRadarPlatformRequest;
use App\Http\Requests\Admin\UpdateRadarSettingsRequest;
use App\Models\Language;
use App\Models\RadarFilter;
use App\Models\RadarPlatform;
use App\Models\RadarProject;
use App\Services\AuditLogger;
use App\Services\Radar\RadarPlatforms;
use App\Services\Radar\RadarSettings;
use App\Services\Radar\TelegramNotifier;
use App\Services\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Radar → Settings (radar.manage): platforms (switch, cadence, hourly limit), scoring weights and thresholds,
 * filter profiles, the Telegram status card with a test message, and re-scoring after weight changes. Credentials
 * themselves live in Settings → Freelance Radar (settings.manage).
 */
class RadarSettingsController extends Controller
{
    public function edit(RadarSettings $settings, TelegramNotifier $telegram, Settings $store, RadarPlatforms $platforms): View
    {
        $platforms->refreshStatuses();

        return view('admin.radar.settings', [
            'settings' => $settings->snapshot(),
            'platforms' => RadarPlatform::query()->orderBy('id')->get(),
            'filters' => RadarFilter::query()->orderBy('sort_order')->orderBy('name')->get(),
            'telegramConfigured' => $telegram->isConfigured(),
            'telegramChat' => $store->get('radar', 'telegram_chat_id'),
            'telegramVerifiedAt' => $store->get(RadarSettings::GROUP, 'telegram_verified_at'),
            'languages' => Language::active()->map(fn (array $language): string => $language['name'])->all(),
        ]);
    }

    public function update(UpdateRadarSettingsRequest $request, RadarSettings $settings, AuditLogger $audit): RedirectResponse
    {
        $before = $settings->snapshot();
        $settings->save($request->settingsData(), $request->user());

        $audit->record(operation: 'radar.settings_updated', user: $request->user(), before: $before, after: $settings->snapshot());

        return to_route('admin.radar.settings')->with('toast', __('Radar settings saved. Use “Re-score recent projects” to apply new weights.'));
    }

    public function platform(UpdateRadarPlatformRequest $request, RadarPlatform $platform, AuditLogger $audit, RadarPlatforms $platforms): RedirectResponse
    {
        $before = $platform->only(['is_enabled', 'fetch_interval_minutes', 'rate_limit_per_hour']);
        $platform->fill($request->validated())->save();
        $platforms->refreshStatuses();

        $audit->record(operation: 'radar.platform_updated', auditable: $platform, user: $request->user(), before: $before, after: $platform->only(['is_enabled', 'fetch_interval_minutes', 'rate_limit_per_hour']));

        return to_route('admin.radar.settings')->with('toast', __(':platform saved.', ['platform' => $platform->name]));
    }

    public function telegramTest(Request $request, TelegramNotifier $telegram, Settings $store, AuditLogger $audit): RedirectResponse
    {
        if (! $telegram->isConfigured()) {
            return back()->with('toast', __('Save the Telegram bot token and chat ID in Settings → Freelance Radar first.'));
        }

        try {
            $telegram->send('*'.TelegramNotifier::escape(__('CodeFanz Freelance Radar')).'*'."\n".TelegramNotifier::escape(__('Test message: alerts will arrive in this chat.')));
        } catch (RadarConnectorException $exception) {
            $audit->record(operation: 'radar.telegram_tested', user: $request->user(), after: ['error' => $exception->getMessage()], result: 'failure');

            return back()->with('toast', __('Telegram test failed: :error', ['error' => $exception->getMessage()]));
        }

        $store->set(RadarSettings::GROUP, 'telegram_verified_at', now()->toIso8601String(), user: $request->user());
        $audit->record(operation: 'radar.telegram_tested', user: $request->user());

        return back()->with('toast', __('Test message sent to Telegram.'));
    }

    public function rescore(Request $request, ScoreRadarProject $score, AuditLogger $audit): RedirectResponse
    {
        $count = 0;

        RadarProject::query()->with('platform')->where('created_at', '>=', now()->subDays(14))->chunkById(200, function ($projects) use ($score, &$count): void {
            foreach ($projects as $project) {
                $score->handle($project);
                $count++;
            }
        });

        $audit->record(operation: 'radar.rescored', user: $request->user(), after: ['projects' => $count]);

        return back()->with('toast', trans_choice('{0} No recent projects to re-score.|{1} :count project re-scored.|[2,*] :count projects re-scored.', $count, ['count' => $count]));
    }
}
