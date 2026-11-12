<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable copy of a prompt template's text at one version. Generations record the version they used.
 */
#[Fillable(['prompt_template_id', 'version', 'system_template', 'user_template', 'variables', 'created_by'])]
class PromptTemplateVersion extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'variables' => 'array',
        ];
    }

    /**
     * @return BelongsTo<PromptTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(PromptTemplate::class, 'prompt_template_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
