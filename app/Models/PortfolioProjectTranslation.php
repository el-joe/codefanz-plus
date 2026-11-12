<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['portfolio_project_id', 'locale', 'title', 'slug', 'summary', 'challenge', 'solution', 'results', 'key_facts', 'seo_title', 'seo_description'])]
class PortfolioProjectTranslation extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'key_facts' => 'array',
        ];
    }

    /**
     * @return BelongsTo<PortfolioProject, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(PortfolioProject::class, 'portfolio_project_id');
    }

    /**
     * Key facts filled in this language, in order.
     *
     * @return list<array{label: string, value: string|null}>
     */
    public function filledKeyFacts(): array
    {
        return array_values(array_filter($this->key_facts ?? [], fn (mixed $fact): bool => is_array($fact) && filled($fact['label'] ?? null)));
    }
}
