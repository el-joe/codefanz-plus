<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Social\GenerateSocialCopy;
use App\Actions\Social\SaveSocialPost;
use App\Enums\AiFeature;
use App\Enums\MediaStatus;
use App\Enums\Permission;
use App\Enums\SocialAccountStatus;
use App\Enums\SocialPlatform;
use App\Enums\SocialPostStatus;
use App\Enums\SocialVariantStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveSocialPostRequest;
use App\Models\AuditLog;
use App\Models\Media;
use App\Models\SocialAccount;
use App\Models\SocialPost;
use App\Models\SocialPostVariant;
use App\Services\Ai\AiGateway;
use App\Services\Social\SocialDelivery;
use App\Services\Social\SocialMetrics;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * B06 Social media publisher: post list, composer (per-account variants with live limits and previews,
 * "Generate with AI"), and the post page with workflow, delivery status and the manual export panel.
 */
class SocialPostController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', 'string', 'in:'.implode(',', array_column(SocialPostStatus::cases(), 'value'))],
            'platform' => ['nullable', 'string', 'in:'.implode(',', array_column(SocialPlatform::cases(), 'value'))],
            'campaign' => ['nullable', 'string', 'max:80'],
        ]);

        $posts = SocialPost::query()
            ->with(['variants', 'author'])
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['platform'] ?? null, fn ($query, $platform) => $query->whereHas('variants', fn ($variants) => $variants->where('platform', $platform)))
            ->when($filters['campaign'] ?? null, fn ($query, $campaign) => $query->where('campaign', $campaign))
            ->latest('updated_at')
            ->paginate(25)
            ->withQueryString();

        return view('admin.social.posts.index', [
            'posts' => $posts,
            'filters' => $filters,
            'campaigns' => $this->campaigns(),
            'needsExport' => SocialPostVariant::query()->where('status', SocialVariantStatus::ReadyForExport)->count(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('admin.social.posts.form', $this->formData(new SocialPost(['locale' => app()->getLocale()]), $request));
    }

    public function store(SaveSocialPostRequest $request, SaveSocialPost $save, GenerateSocialCopy $generate): RedirectResponse
    {
        $post = $save->handle(new SocialPost, [...$request->validated(), 'planned_at' => $request->plannedFor()], $request->user());

        return $this->afterSave($request, $post, $generate, __('Social post saved as a draft.'));
    }

    public function show(Request $request, SocialPost $post, SocialMetrics $metrics, SocialDelivery $delivery): View
    {
        $post->load(['variants.account', 'variants.latestSnapshot', 'variants.generation', 'author', 'reviewer']);
        $media = $post->mediaIn(SocialPost::MEDIA_COLLECTION)->with('variants')->get();

        return view('admin.social.posts.show', [
            'post' => $post,
            'media' => $media,
            'metrics' => $metrics,
            'paused' => $delivery->publishingPaused($post),
            'canReview' => $request->user()->can('review', $post),
            'audit' => AuditLog::query()
                ->where('auditable_type', $post->getMorphClass())
                ->where('auditable_id', $post->id)
                ->latest('id')
                ->limit(20)
                ->get(),
        ]);
    }

    public function edit(Request $request, SocialPost $post): View|RedirectResponse
    {
        if (! $post->status->isEditable()) {
            return redirect()->route('admin.social.posts.show', $post)->with('toast', __('This post can no longer be edited while it is “:status”.', ['status' => $post->status->label()]));
        }

        $post->load('variants');

        return view('admin.social.posts.form', $this->formData($post, $request));
    }

    public function update(SaveSocialPostRequest $request, SocialPost $post, SaveSocialPost $save, GenerateSocialCopy $generate): RedirectResponse
    {
        $wasApproved = in_array($post->status, [SocialPostStatus::Approved, SocialPostStatus::Scheduled], true);
        $post = $save->handle($post, [...$request->validated(), 'planned_at' => $request->plannedFor()], $request->user());

        return $this->afterSave($request, $post, $generate, $wasApproved
            ? __('Saved. The post went back to draft and needs a new approval.')
            : __('Social post saved.'));
    }

    /**
     * Download the original of an attached image (manual export).
     */
    public function media(SocialPost $post, Media $media): StreamedResponse
    {
        Gate::authorize('view', $post);

        abort_unless($post->mediaIn(SocialPost::MEDIA_COLLECTION)->whereKey($media->id)->exists(), 404);
        abort_unless(Storage::disk($media->original_disk)->exists($media->original_path), 404);

        return Storage::disk($media->original_disk)->download($media->original_path, $media->original_name);
    }

    private function afterSave(SaveSocialPostRequest $request, SocialPost $post, GenerateSocialCopy $generate, string $message): RedirectResponse
    {
        if (! $request->boolean('ai')) {
            return redirect()->route('admin.social.posts.show', $post)->with('toast', $message);
        }

        abort_unless($request->user()->can(Permission::AiUse->value), 403);

        $topic = trim((string) $request->validated('topic'));

        if ($topic === '') {
            return redirect()->route('admin.social.posts.edit', $post)->withErrors(['topic' => __('Describe what the post is about.')])->with('toast', $message);
        }

        try {
            $generate->handle($post, $request->user(), $topic);
        } catch (ValidationException $exception) {
            return redirect()->route('admin.social.posts.edit', $post)->withErrors($exception->errors())->with('toast', $message);
        }

        return redirect()->route('admin.social.posts.edit', $post)->with('toast', __('AI copy added as a draft. Review and edit it before submitting.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(SocialPost $post, Request $request): array
    {
        return [
            'post' => $post,
            'accounts' => SocialAccount::query()
                ->where('status', '!=', SocialAccountStatus::Disconnected)
                ->orderBy('platform')
                ->orderBy('name')
                ->get(),
            'images' => Media::query()
                ->where('status', MediaStatus::Ready)
                ->where('mime_type', 'like', 'image/%')
                ->with('variants')
                ->latest('id')
                ->limit(60)
                ->get(),
            'selectedMedia' => $post->exists ? $post->mediaIn(SocialPost::MEDIA_COLLECTION)->pluck('media.id')->all() : [],
            'campaigns' => $this->campaigns(),
            'aiReason' => $request->user()->can(Permission::AiUse->value) ? app(AiGateway::class)->unavailableReason(AiFeature::Social, $request->user()->id) : null,
            'canUseAi' => $request->user()->can(Permission::AiUse->value),
        ];
    }

    /**
     * @return list<string>
     */
    private function campaigns(): array
    {
        return SocialPost::query()->whereNotNull('campaign')->distinct()->orderBy('campaign')->limit(100)->pluck('campaign')->all();
    }
}
