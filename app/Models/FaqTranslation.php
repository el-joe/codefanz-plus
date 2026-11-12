<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

#[Fillable(['faq_id', 'locale', 'question', 'answer'])]
class FaqTranslation extends Model
{
    /**
     * Sanitized HTML answer (raw HTML stripped, unsafe links removed).
     */
    public function answerHtml(): string
    {
        return Str::markdown((string) $this->answer, ['html_input' => 'strip', 'allow_unsafe_links' => false]);
    }

    /**
     * Plain-text answer for structured data.
     */
    public function answerText(): string
    {
        return trim(html_entity_decode(strip_tags($this->answerHtml()), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}
