<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SocialAccountStatus;
use App\Enums\SocialPlatform;
use App\Exceptions\SocialApiException;
use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Services\AuditLogger;
use App\Services\Social\SocialApps;
use App\Services\Social\SocialOAuth;
use App\Services\Social\SocialPublisherManager;
use App\Services\Social\SocialTokenRefresher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * B06/B11 social accounts: honest status per app and account, OAuth connect flows, verification, manual-mode
 * accounts for platforms (or apps) without publishing access, and disconnect.
 */
class SocialAccountController extends Controller
{
    public function __construct(private SocialApps $apps, private AuditLogger $audit) {}

    public function index(): View
    {
        $accounts = SocialAccount::query()->withCount('variants')->orderBy('platform')->orderBy('name')->get();

        $apps = collect($this->apps->providers())->mapWithKeys(fn (string $provider): array => [$provider => [
            'label' => $this->apps->label($provider),
            'configured' => $this->apps->isConfigured($provider),
            'status' => $this->apps->status($provider),
            'outcome' => $this->apps->lastOutcome($provider),
            'redirect' => $this->apps->redirectUri($provider),
            'platforms' => SocialPlatform::forProvider($provider),
            'scopes' => $this->apps->scopes($provider),
        ]])->all();

        return view('admin.social.accounts', ['accounts' => $accounts, 'apps' => $apps]);
    }

    /**
     * Add an account that is always posted to by hand (TikTok, YouTube, or any platform while the app awaits review).
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'platform' => ['required', Rule::enum(SocialPlatform::class)],
            'name' => ['required', 'string', 'max:150'],
            'profile_url' => ['nullable', 'url:https', 'max:500'],
        ]);

        $account = SocialAccount::create([
            'platform' => $validated['platform'],
            'name' => $validated['name'],
            'profile_url' => $validated['profile_url'] ?? null,
            'status' => SocialAccountStatus::ManualMode,
            'connected_by' => $request->user()->id,
        ]);

        $this->audit->record(operation: 'social_account.manual_added', auditable: $account, user: $request->user(), after: ['platform' => $account->platform->value, 'name' => $account->name]);

        return back()->with('toast', __('Manual account added. Its posts are prepared for manual export.'));
    }

    public function connect(Request $request, string $provider, SocialOAuth $oauth): RedirectResponse
    {
        abort_unless($this->apps->exists($provider), 404);

        try {
            $url = $oauth->authorizationUrl($provider, $request->user());
        } catch (ValidationException $exception) {
            return redirect()->route('admin.social.accounts.index')->with('toast', collect($exception->errors())->flatten()->first());
        }

        $this->audit->record(operation: 'social_account.connect_started', user: $request->user(), after: ['provider' => $provider]);

        return redirect()->away($url);
    }

    public function callback(Request $request, string $provider, SocialOAuth $oauth): RedirectResponse
    {
        abort_unless($this->apps->exists($provider), 404);

        try {
            $verifier = $oauth->consumeState($provider, $request->query('state'), $request->user());
        } catch (ValidationException $exception) {
            $this->audit->record(operation: 'social_account.connect_rejected', user: $request->user(), after: ['provider' => $provider, 'reason' => 'invalid_state'], result: 'failure');

            return redirect()->route('admin.social.accounts.index')->with('toast', collect($exception->errors())->flatten()->first());
        }

        if ($request->filled('error') || ! $request->filled('code')) {
            $this->audit->record(operation: 'social_account.connect_rejected', user: $request->user(), after: ['provider' => $provider, 'reason' => mb_substr((string) $request->query('error', 'no_code'), 0, 100)], result: 'failure');

            return redirect()->route('admin.social.accounts.index')->with('toast', __('The connection was cancelled on :app.', ['app' => $this->apps->label($provider)]));
        }

        try {
            $accounts = $oauth->complete($provider, (string) $request->query('code'), $verifier, $request->user());
        } catch (SocialApiException $exception) {
            $this->audit->record(operation: 'social_account.connect_failed', user: $request->user(), after: ['provider' => $provider, 'kind' => $exception->kind, 'error' => $exception->getMessage()], result: 'failure');

            return redirect()->route('admin.social.accounts.index')->with('toast', __(':app connection failed: :error', ['app' => $this->apps->label($provider), 'error' => $exception->getMessage()]));
        }

        $this->audit->record(operation: 'social_account.connected', user: $request->user(), after: ['provider' => $provider, 'accounts' => $accounts->map(fn (SocialAccount $account): array => ['id' => $account->id, 'platform' => $account->platform->value, 'status' => $account->status->value])->all()]);

        return redirect()->route('admin.social.accounts.index')->with('toast', $accounts->isEmpty() ? __('No accounts were found on :app.', ['app' => $this->apps->label($provider)]) : __('Accounts connected from :app: :count.', ['count' => $accounts->count(), 'app' => $this->apps->label($provider)]));
    }

    public function verify(Request $request, SocialAccount $account, SocialPublisherManager $publishers, SocialTokenRefresher $tokens): RedirectResponse
    {
        $publisher = $publishers->for($account->platform);

        if ($publisher === null || blank($account->access_token)) {
            return back()->with('toast', __('This account has no API connection to verify.'));
        }

        try {
            if ($tokens->needsRefresh($account) || $account->tokenExpired()) {
                $account = $tokens->refresh($account);
            }

            if (blank($account->access_token)) {
                return back()->with('toast', __('The token could not be refreshed. Reconnect the account.'));
            }

            $publisher->verify($account);
        } catch (SocialApiException $exception) {
            $status = match ($exception->kind) {
                SocialApiException::AUTH => SocialAccountStatus::TokenExpired,
                SocialApiException::APP_APPROVAL => SocialAccountStatus::RequiresAppApproval,
                default => $account->status,
            };
            $account->forceFill(['status' => $status, 'last_error' => $exception->getMessage()])->save();
            $this->audit->record(operation: 'social_account.verified', auditable: $account, user: $request->user(), after: ['status' => $status->value, 'error' => $exception->getMessage()], result: 'failure');

            return back()->with('toast', __('Verification failed: :error', ['error' => $exception->getMessage()]));
        }

        $account->forceFill(['last_verified_at' => now(), 'last_error' => null])->save();
        $this->audit->record(operation: 'social_account.verified', auditable: $account, user: $request->user(), after: ['status' => $account->status->value]);

        return back()->with('toast', __('Connection verified.'));
    }

    /**
     * Stop publishing through the API but keep the account for manual exports.
     */
    public function manual(Request $request, SocialAccount $account): RedirectResponse
    {
        $before = $account->status->value;
        $account->forgetTokens(SocialAccountStatus::ManualMode);
        $this->audit->record(operation: 'social_account.manual_mode', auditable: $account, user: $request->user(), before: ['status' => $before], after: ['status' => SocialAccountStatus::ManualMode->value]);

        return back()->with('toast', __('Switched to manual export. Tokens were removed.'));
    }

    public function destroy(Request $request, SocialAccount $account): RedirectResponse
    {
        $before = $account->status->value;
        $account->forgetTokens(SocialAccountStatus::Disconnected);

        if (! $account->variants()->exists()) {
            $account->delete();
        }

        $this->audit->record(operation: 'social_account.disconnected', auditable: $account, user: $request->user(), before: ['status' => $before], after: ['status' => SocialAccountStatus::Disconnected->value]);

        return back()->with('toast', __('Account disconnected. Tokens were removed.'));
    }
}
