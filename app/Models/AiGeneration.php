<?php

namespace App\Models;

use App\Enums\AiGenerationStatus;
use App\Enums\PromptPurpose;
use Database\Factories\AiGenerationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One Content Studio generation: template version, inputs, structured output (edited by staff before use), tokens,
 * estimated cost, guardrail flags and what it became — a blog draft (post_id) or change requests. Private: nothing
 * here is public until a human approves it through the editorial workflow or the approval queue.
 *
 * Output shape: {locale: {field: text}}.
 */
#[Fillable([
    'prompt_template_id', 'template_version', 'purpose', 'mode', 'target_type', 'target_id', 'locales', 'inputs',
    'output', 'guardrail_flags', 'provider', 'model', 'input_tokens', 'output_tokens', 'estimated_cost_minor', 'status',
    'is_dry_run', 'error', 'user_id', 'post_id',
])]
class AiGeneration extends Model
{
    /** @use HasFactory<AiGenerationFactory> */
    use HasFactory, HasUlids;

    /**
     * Display order of output fields.
     */
    private const FIELD_ORDER = ['title', 'name', 'subject', 'headline', 'tagline', 'preheader', 'excerpt', 'summary', 'text', 'description', 'body', 'challenge', 'solution', 'results', 'hashtags', 'call_to_action', 'seo_title', 'seo_description'];

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'purpose' => PromptPurpose::class,
            'status' => AiGenerationStatus::class,
            'locales' => 'array',
            'inputs' => 'array',
            'output' => 'array',
            'guardrail_flags' => 'array',
            'is_dry_run' => 'boolean',
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'estimated_cost_minor' => 'float',
            'template_version' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<PromptTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(PromptTemplate::class, 'prompt_template_id')->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Post, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function target(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return HasMany<ChangeRequest, $this>
     */
    public function changeRequests(): HasMany
    {
        return $this->hasMany(ChangeRequest::class);
    }

    /**
     * Output in the order the languages were requested (MySQL JSON columns sort object keys).
     *
     * @return array<string, array<string, string>>
     */
    public function orderedOutput(): array
    {
        $priority = array_flip(self::FIELD_ORDER);
        $output = array_map(function ($fields) use ($priority): array {
            $fields = (array) $fields;
            uksort($fields, fn (string $a, string $b): int => ($priority[$a] ?? 99) <=> ($priority[$b] ?? 99));

            return $fields;
        }, (array) $this->output);
        $ordered = [];

        foreach ((array) $this->locales as $locale) {
            if (isset($output[$locale])) {
                $ordered[$locale] = $output[$locale];
            }
        }

        return $ordered + $output;
    }

    /**
     * A short line for lists: the first title-like field of the first language.
     */
    public function headline(): ?string
    {
        $fields = (array) collect($this->orderedOutput())->first();

        foreach (['title', 'name', 'seo_title', 'subject', 'headline', 'text'] as $field) {
            if (filled($fields[$field] ?? null)) {
                return mb_substr((string) $fields[$field], 0, 120);
            }
        }

        return null;
    }

    public function hasFlags(): bool
    {
        return ! empty($this->guardrail_flags);
    }
}
