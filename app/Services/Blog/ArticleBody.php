<?php

namespace App\Services\Blog;

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Turns editor HTML into the stored article body: sanitized, with stable anchor ids on H2/H3 headings,
 * plus the derived table of contents, word count and reading time.
 */
class ArticleBody
{
    public const WORDS_PER_MINUTE = 200;

    public function __construct(private HtmlSanitizer $sanitizer) {}

    /**
     * @return array{body: string, toc: list<array{level: int, id: string, text: string}>, word_count: int, reading_minutes: int}
     */
    public function prepare(?string $html): array
    {
        $clean = $this->sanitizer->sanitize($html);

        if ($clean === '') {
            return ['body' => '', 'toc' => [], 'word_count' => 0, 'reading_minutes' => 1];
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head><body><div id="cf-root">'.$clean.'</div></body></html>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementById('cf-root');
        $toc = [];
        $used = [];

        foreach ((new DOMXPath($document))->query('.//h2 | .//h3', $root) ?: [] as $heading) {
            if (! $heading instanceof DOMElement) {
                continue;
            }

            $text = trim(preg_replace('/\s+/u', ' ', $heading->textContent) ?? '');

            if ($text === '') {
                continue;
            }

            $id = $this->uniqueId($this->anchor($text), $used);
            $heading->setAttribute('id', $id);
            $toc[] = ['level' => (int) substr($heading->nodeName, 1), 'id' => $id, 'text' => mb_substr($text, 0, 150)];
        }

        $body = '';

        foreach (iterator_to_array($root->childNodes) as $child) {
            $body .= $document->saveHTML($child);
        }

        $text = '';

        foreach ((new DOMXPath($document))->query('.//text()', $root) ?: [] as $textNode) {
            $text .= ' '.$textNode->textContent;
        }

        $words = self::countWords($text);

        return [
            // Editors add an empty trailing paragraph after blocks; it carries no content.
            'body' => trim(preg_replace('/(?:<p><\/p>\s*)+$/', '', trim($body)) ?? $body),
            'toc' => $toc,
            'word_count' => $words,
            'reading_minutes' => max(1, (int) ceil($words / self::WORDS_PER_MINUTE)),
        ];
    }

    public static function countWords(string $text): int
    {
        $parts = preg_split('/[^\p{L}\p{N}\p{M}\'’-]+/u', $text, -1, PREG_SPLIT_NO_EMPTY);

        return $parts === false ? 0 : count($parts);
    }

    /**
     * Unicode-aware anchor: Arabic headings keep their letters ("لماذا-لارافيل").
     */
    public function anchor(string $text): string
    {
        $slug = trim(preg_replace('/[^\p{L}\p{N}]+/u', '-', mb_strtolower($text)) ?? '', '-');

        return mb_substr($slug, 0, 80) ?: 'section';
    }

    /**
     * @param  array<string, true>  $used
     */
    private function uniqueId(string $base, array &$used): string
    {
        $id = $base;
        $suffix = 2;

        while (isset($used[$id])) {
            $id = $base.'-'.$suffix++;
        }

        $used[$id] = true;

        return $id;
    }
}
