<?php

namespace App\Actions\Social;

use App\Actions\Ai\GenerateContent;
use App\Enums\AiGenerationStatus;
use App\Enums\PostOrigin;
use App\Enums\PromptPurpose;
use App\Enums\SocialPostStatus;
use App\Models\PromptTemplate;
use App\Models\SocialPost;
use App\Models\SocialPostVariant;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Validation\ValidationException;

/**
 * "Generate with AI" in the composer: runs the Content Studio social_post template once per variant through
 * GenerateContent (so AiGateway enforces the ai_social / ai_automation kill switches, budget and caps and every
 * call is logged), then fills the variants as AI-origin draft copy. The post stays a draft; the person who
 * generated it can never approve it.
 */
class GenerateSocialCopy
{
    public function __construct(private GenerateContent $generate, private AuditLogger $audit) {}

    public function handle(SocialPost $post, User $user, string $topic): SocialPost
    {
        if (! in_array($post->status, [SocialPostStatus::Draft, SocialPostStatus::ChangesRequested], true)) {
            throw ValidationException::withMessages(['ai' => __('AI copy can only be generated for drafts.')]);
        }

        $post->loadMissing('variants');

        if ($post->variants->isEmpty()) {
            throw ValidationException::withMessages(['ai' => __('Choose at least one account before generating copy.')]);
        }

        $template = PromptTemplate::query()
            ->where('purpose', PromptPurpose::SocialPost)
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('locale')->orWhere('locale', $post->locale))
            ->orderByRaw('locale is null')
            ->orderBy('id')
            ->first();

        if ($template === null) {
            throw ValidationException::withMessages(['ai' => __('There is no active social post template in the prompt library.')]);
        }

        $generated = [];

        foreach ($post->variants as $variant) {
            $generation = $this->generate->handle($template, $user, [
                'platform' => $variant->platform->label().' ('.__('max :count characters', ['count' => $variant->platform->maxLength()]).')',
                'topic' => $topic,
                'link' => (string) $post->link_url,
            ], [$post->locale]);

            $output = (array) ($generation->output[$post->locale] ?? []);
            $text = trim((string) ($output['text'] ?? ''));
            $cta = trim((string) ($output['call_to_action'] ?? ''));

            if ($cta !== '' && ! str_contains($text, $cta)) {
                $text .= "\n\n".$cta;
            }

            $variant->forceFill([
                'text' => $text,
                'hashtags' => implode(' ', SocialPostVariant::parseHashtags((string) ($output['hashtags'] ?? ''))) ?: null,
                'ai_generation_id' => $generation->id,
            ])->save();

            $generation->forceFill(['status' => AiGenerationStatus::SocialDraftCreated])->save();
            $generated[] = $generation->id;
        }

        $post->forceFill([
            'origin' => PostOrigin::Ai,
            'generated_by' => $user->getKey(),
            'lock_version' => $post->lock_version + 1,
        ])->save();

        $this->audit->record(operation: 'social_post.ai_generated', auditable: $post, user: $user, after: ['generations' => $generated, 'template' => $template->key, 'version' => $template->version]);

        return $post->refresh()->load('variants');
    }
}
