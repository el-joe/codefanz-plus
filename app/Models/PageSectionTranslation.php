<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['page_section_id', 'locale', 'eyebrow', 'heading', 'subheading', 'cta_label', 'points', 'items'])]
class PageSectionTranslation extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'points' => 'array',
            'items' => 'array',
        ];
    }
}
