<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Renders a legal page body: sanitized Markdown whose level-2 headings become numbered clauses with
 * stable anchors (clause-1, clause-2…), plus the matching table of contents.
 */
class LegalDocument
{
    /**
     * @return array{html: string, toc: list<array{id: string, number: int, title: string}>}
     */
    public function render(string $markdown): array
    {
        $html = Str::markdown($markdown, ['html_input' => 'strip', 'allow_unsafe_links' => false]);
        $toc = [];

        $html = preg_replace_callback('#<h2>(.*?)</h2>#s', function (array $match) use (&$toc): string {
            $number = count($toc) + 1;
            $id = 'clause-'.$number;
            // Authors may number clauses themselves ("1. Scope"); numbering is applied once, consistently.
            $inner = preg_replace('/^\s*\d+[.)]\s*/u', '', $match[1]) ?? $match[1];
            $title = trim(html_entity_decode(strip_tags($inner), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

            $toc[] = ['id' => $id, 'number' => $number, 'title' => $title];

            return sprintf('<h2 id="%s" tabindex="-1"><span class="clause-number">%d.</span> %s</h2>', $id, $number, $inner);
        }, $html) ?? $html;

        return ['html' => $html, 'toc' => $toc];
    }
}
