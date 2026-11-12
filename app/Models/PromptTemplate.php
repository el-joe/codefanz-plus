<?php

namespace App\Models;

use App\Enums\PromptPurpose;
use Database\Factories\PromptTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A reusable prompt in the AI prompt library. The system template may only use the trusted built-in variables
 * (brand voice, glossary, language…); the user template declares the variables staff fill in, which are always
 * passed to the model as delimited data. Every text change creates a new version.
 */
#[Fillable(['key', 'name', 'purpose', 'locale', 'description', 'system_template', 'user_template', 'variables', 'version', 'is_active', 'created_by', 'updated_by'])]
class PromptTemplate extends Model
{
    /** @use HasFactory<PromptTemplateFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Variables filled by the application from trusted configuration, usable in the system template.
     */
    public const SYSTEM_VARIABLES = ['brand_name', 'brand_voice', 'audience', 'do_rules', 'dont_rules', 'banned_claims', 'glossary', 'language', 'languages'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'version' => 1,
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'purpose' => PromptPurpose::class,
            'variables' => 'array',
            'version' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<PromptTemplateVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(PromptTemplateVersion::class);
    }

    /**
     * @return HasMany<AiGeneration, $this>
     */
    public function generations(): HasMany
    {
        return $this->hasMany(AiGeneration::class);
    }

    /**
     * @param  Builder<PromptTemplate>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @return list<array{name: string, label: string, required: bool, max: int}>
     */
    public function declaredVariables(): array
    {
        return array_values(array_map(fn (array $variable): array => [
            'name' => (string) $variable['name'],
            'label' => (string) ($variable['label'] ?? $variable['name']),
            'required' => (bool) ($variable['required'] ?? false),
            'max' => (int) ($variable['max'] ?? 2000),
        ], (array) $this->variables));
    }

    /**
     * Variable names used as {{name}} in a template text.
     *
     * @return list<string>
     */
    public static function placeholders(string $text): array
    {
        preg_match_all('/\{\{\s*([A-Za-z0-9_]+)\s*\}\}/', $text, $matches);

        return array_values(array_unique($matches[1]));
    }
}
