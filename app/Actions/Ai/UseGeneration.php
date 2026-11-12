<?php

namespace App\Actions\Ai;

use App\Actions\Blog\SavePost;
use App\Actions\Governance\ProposeChange;
use App\Contracts\Approvable;
use App\Enums\AiGenerationStatus;
use App\Enums\ChangeOrigin;
use App\Enums\PostOrigin;
use App\Enums\PromptPurpose;
use App\Models\AiGeneration;
use App\Models\BrandProfile;
use App\Models\ChangeRequest;
use App\Models\Post;
use App\Models\PostTranslation;
use App\Models\User;
use App\Services\Ai\ContentGuardrails;
use App\Services\Ai\StudioTargets;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Turns a reviewed (and possibly edited) generation into something: a new blog post Draft with origin "ai" that
 * still has to pass the editorial workflow, pending change requests for an existing item (one per language, origin
 * "ai", never applied here), or a private saved draft. Guardrails run again on the edited text and are stored on
 * each change request for reviewers.
 */
class UseGeneration
{
    public function __construct(
        private SavePost $savePost,
        private ProposeChange $propose,
        private ContentGuardrails $guardrails,
        private StudioTargets $targets,
        private AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, array<string, string|null>>  $edited
     */
    public function createBlogDraft(AiGeneration $generation, array $edited, User $user): Post
    {
        $this->ensureOpen($generation, PromptPurpose::BlogDraft);
        $output = $this->merge($generation, $edited);

        return DB::transaction(function () use ($generation, $output, $user): Post {
            $translations = [];

            foreach ($output as $locale => $fields) {
                if (blank($fields['title'] ?? null)) {
                    continue;
                }

                $translations[$locale] = [
                    'title' => $fields['title'],
                    'slug' => $this->uniquePostSlug($locale, (string) $fields['title']),
                    'excerpt' => $fields['excerpt'] ?? null,
                    'body' => $this->markdownToHtml((string) ($fields['body'] ?? '')),
                    'seo_title' => $fields['seo_title'] ?? null,
                    'seo_description' => $fields['seo_description'] ?? null,
                ];
            }

            if ($translations === []) {
                throw ValidationException::withMessages(['outputs' => __('Add a title before creating the draft.')]);
            }

            $post = $this->savePost->handle(null, [
                'origin' => PostOrigin::Ai,
                'is_single_language' => count($translations) === 1,
                'translations' => $translations,
            ], $user)['post'];

            $generation->forceFill([
                'output' => $output,
                'guardrail_flags' => $this->flags($generation, $output),
                'status' => AiGenerationStatus::DraftCreated,
                'post_id' => $post->id,
            ])->save();

            $this->audit->record(operation: 'ai_generation.draft_created', auditable: $generation, user: $user, after: ['post_id' => $post->id]);

            return $post;
        });
    }

    /**
     * @param  array<string, array<string, string|null>>  $edited
     * @return Collection<int, ChangeRequest>
     */
    public function propose(AiGeneration $generation, array $edited, User $user): Collection
    {
        if (! $generation->purpose->needsTarget() || ! $generation->status->isOpen() || $generation->is_dry_run) {
            throw ValidationException::withMessages(['generation' => __('This generation cannot be sent to approval.')]);
        }

        $generation->loadMissing('target.translations');
        $target = $generation->target;

        if (! $target instanceof Approvable) {
            throw ValidationException::withMessages(['generation' => __('The website item no longer exists.')]);
        }

        /** @var Model&Approvable $target */
        $output = $this->merge($generation, $edited);
        $type = $this->targets->typeOf($target);
        $htmlFields = $this->targets->htmlFields($type);

        return DB::transaction(function () use ($generation, $output, $user, $target, $type, $htmlFields): Collection {
            $created = collect();
            $flags = $this->flags($generation, $output);

            foreach ($output as $locale => $fields) {
                $snapshot = $target->approvableSnapshot($locale);
                $proposed = [];

                foreach ($fields as $field => $value) {
                    if (! in_array($field, $target->approvableAttributes(), true) || blank($value)) {
                        continue;
                    }

                    $value = in_array($field, $htmlFields, true) ? $this->markdownToHtml((string) $value) : (string) $value;
                    $current = (string) ($snapshot[$field] ?? '');

                    if (trim($value) !== trim($current)) {
                        $proposed[$field] = $value;
                    }
                }

                // A new translation needs its own URL slug.
                if ($proposed !== [] && blank($snapshot['slug'] ?? null) && in_array('slug', $target->approvableAttributes(), true)) {
                    $titleField = in_array('name', $target->approvableAttributes(), true) ? 'name' : 'title';
                    $proposed['slug'] = $this->uniqueSlug($target, $locale, (string) ($fields[$titleField] ?? $this->targets->titleOf($target)));
                }

                if ($proposed === []) {
                    continue;
                }

                $created->push($this->propose->handle(
                    target: $target,
                    proposed: $proposed,
                    origin: ChangeOrigin::Ai,
                    summary: mb_substr(__(':purpose for “:title” (:locale)', ['purpose' => $generation->purpose->label(), 'title' => $this->targets->titleOf($target), 'locale' => strtoupper($locale)]), 0, 255),
                    requestedBy: $user,
                    actorLabel: __('AI Content Studio').' · '.$user->name,
                    locale: $locale,
                    guardrailFlags: array_values(array_filter($flags, fn (array $flag): bool => $flag['locale'] === $locale)),
                    aiGenerationId: $generation->id,
                ));
            }

            if ($created->isEmpty()) {
                throw ValidationException::withMessages(['outputs' => __('Nothing differs from the live content, so there is nothing to approve.')]);
            }

            $generation->forceFill([
                'output' => $output,
                'guardrail_flags' => $flags,
                'status' => AiGenerationStatus::Proposed,
            ])->save();

            $this->audit->record(operation: 'ai_generation.proposed', auditable: $generation, user: $user, after: ['target' => $type.':'.$target->getKey(), 'change_requests' => $created->pluck('reference')->all()]);

            return $created;
        });
    }

    /**
     * @param  array<string, array<string, string|null>>  $edited
     */
    public function save(AiGeneration $generation, array $edited, User $user): AiGeneration
    {
        if (! $generation->status->isOpen() || $generation->is_dry_run) {
            throw ValidationException::withMessages(['generation' => __('This generation can no longer be edited.')]);
        }

        $output = $this->merge($generation, $edited);
        $generation->forceFill(['output' => $output, 'guardrail_flags' => $this->flags($generation, $output), 'status' => AiGenerationStatus::Saved])->save();
        $this->audit->record(operation: 'ai_generation.saved', auditable: $generation, user: $user);

        return $generation;
    }

    public function discard(AiGeneration $generation, User $user): void
    {
        if (! $generation->status->isOpen()) {
            throw ValidationException::withMessages(['generation' => __('This generation can no longer be discarded.')]);
        }

        $generation->forceFill(['status' => AiGenerationStatus::Discarded])->save();
        $this->audit->record(operation: 'ai_generation.discarded', auditable: $generation, user: $user);
    }

    private function ensureOpen(AiGeneration $generation, PromptPurpose $purpose): void
    {
        if ($generation->purpose !== $purpose || ! $generation->status->isOpen() || $generation->is_dry_run) {
            throw ValidationException::withMessages(['generation' => __('This generation cannot be used for that.')]);
        }
    }

    /**
     * Staff edits replace generated values for known locales and fields only.
     *
     * @param  array<string, array<string, string|null>>  $edited
     * @return array<string, array<string, string>>
     */
    private function merge(AiGeneration $generation, array $edited): array
    {
        $output = (array) $generation->output;

        foreach ($output as $locale => $fields) {
            foreach (array_keys((array) $fields) as $field) {
                if (array_key_exists($field, $edited[$locale] ?? [])) {
                    $output[$locale][$field] = mb_substr(trim(strip_tags((string) $edited[$locale][$field])), 0, $this->targets->maxLength($field));
                }
            }
        }

        return $output;
    }

    /**
     * @param  array<string, array<string, string>>  $output
     * @return list<array{type: string, locale: string, field: string, excerpt: string}>
     */
    private function flags(AiGeneration $generation, array $output): array
    {
        $generation->loadMissing('target.translations');
        $known = array_map('strval', array_filter((array) $generation->inputs, 'is_scalar'));

        if ($generation->target instanceof Model) {
            $locales = array_keys((array) $generation->target->translations->keyBy('locale')->all());
            $known[] = (string) json_encode($this->targets->currentContent($generation->target, $this->targets->fieldsFor($generation->purpose, $generation->target_type), $locales), JSON_UNESCAPED_UNICODE);
        }

        return $this->guardrails->check($output, implode("\n", $known), BrandProfile::current());
    }

    private function markdownToHtml(string $markdown): string
    {
        return $markdown === '' ? '' : trim(Str::markdown($markdown, ['html_input' => 'strip', 'allow_unsafe_links' => false]));
    }

    private function uniquePostSlug(string $locale, string $title): string
    {
        $base = Str::slug($title, '-', null) ?: 'article';
        $base = in_array($base, Post::RESERVED_SLUGS, true) ? $base.'-1' : mb_substr($base, 0, 180);
        $slug = $base;

        for ($suffix = 2; PostTranslation::query()->where('locale', $locale)->where('slug', $slug)->exists(); $suffix++) {
            $slug = $base.'-'.$suffix;
        }

        return $slug;
    }

    private function uniqueSlug(Model $target, string $locale, string $title): string
    {
        $base = mb_substr(Str::slug($title, '-', null) ?: $target->getMorphClass().'-'.$target->getKey(), 0, 180);
        $slug = $base;
        $query = fn (string $candidate) => $target->translations()->getRelated()->newQuery()->where('locale', $locale)->where('slug', $candidate)->exists();

        for ($suffix = 2; $query($slug); $suffix++) {
            $slug = $base.'-'.$suffix;
        }

        return $slug;
    }
}
