<?php

namespace App\Services\Blog;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * Strict allow-list HTML sanitizer for article bodies (TipTap output or pasted HTML).
 *
 * Elements outside the allow-list are unwrapped (their text survives); dangerous containers (script, style, svg,
 * forms…) are dropped with their content. Every attribute is checked against a per-element allow-list, URLs must be
 * https or site-relative, and iframes survive only as privacy-enhanced YouTube/Vimeo embeds rebuilt from scratch.
 */
class HtmlSanitizer
{
    /**
     * Allowed elements and their allowed attributes.
     *
     * @var array<string, list<string>>
     */
    private const ALLOWED = [
        'p' => ['dir'],
        'br' => [],
        'hr' => [],
        'h2' => ['dir'],
        'h3' => ['dir'],
        'h4' => ['dir'],
        'strong' => [],
        'b' => [],
        'em' => [],
        'i' => [],
        'u' => [],
        's' => [],
        'sub' => [],
        'sup' => [],
        'mark' => [],
        'code' => ['class'],
        'pre' => ['dir'],
        'blockquote' => ['dir'],
        'ul' => ['dir'],
        'ol' => ['dir', 'start'],
        'li' => ['dir'],
        'a' => ['href', 'title'],
        'img' => ['src', 'alt', 'title', 'width', 'height', 'data-media-id'],
        'figure' => [],
        'figcaption' => ['dir'],
        'table' => ['dir'],
        'thead' => [],
        'tbody' => [],
        'tr' => [],
        'th' => ['colspan', 'rowspan', 'scope'],
        'td' => ['colspan', 'rowspan'],
        'aside' => ['data-callout', 'dir'],
    ];

    /**
     * Elements removed together with everything inside them.
     *
     * @var list<string>
     */
    private const DROP_WITH_CONTENT = [
        'script', 'style', 'iframe', 'frame', 'frameset', 'object', 'embed', 'applet', 'svg', 'math', 'template',
        'noscript', 'noembed', 'noframes', 'form', 'input', 'textarea', 'select', 'option', 'button', 'link', 'meta',
        'base', 'title', 'head', 'xml', 'audio', 'video', 'source', 'track', 'canvas', 'portal', 'dialog',
    ];

    /**
     * Elements renamed to a supported equivalent (articles start at H2 below the page title).
     *
     * @var array<string, string>
     */
    private const RENAME = ['h1' => 'h2', 'h5' => 'h4', 'h6' => 'h4'];

    public const CALLOUT_TYPES = ['info', 'tip', 'warning'];

    public function sanitize(?string $html): string
    {
        $html = trim((string) $html);

        if ($html === '') {
            return '';
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head><body><div id="cf-root">'.$html.'</div></body></html>',
            LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_COMPACT,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementById('cf-root');

        if (! $root instanceof DOMElement) {
            return '';
        }

        $this->cleanChildren($root);

        $output = '';

        foreach (iterator_to_array($root->childNodes) as $child) {
            $output .= $document->saveHTML($child);
        }

        return trim($output);
    }

    private function cleanChildren(DOMNode $parent): void
    {
        foreach (iterator_to_array($parent->childNodes) as $child) {
            $this->cleanNode($child);
        }
    }

    private function cleanNode(DOMNode $node): void
    {
        if ($node instanceof DOMText) {
            return;
        }

        if (! $node instanceof DOMElement) {
            // Comments, CDATA, processing instructions, entity references.
            $node->parentNode?->removeChild($node);

            return;
        }

        $tag = strtolower($node->localName ?? $node->nodeName);

        if ($tag === 'iframe') {
            $this->replaceEmbed($node);

            return;
        }

        if (in_array($tag, self::DROP_WITH_CONTENT, true) || str_contains($tag, ':')) {
            $node->parentNode?->removeChild($node);

            return;
        }

        if (isset(self::RENAME[$tag])) {
            $node = $this->rename($node, self::RENAME[$tag]);
            $tag = self::RENAME[$tag];
        }

        if (! array_key_exists($tag, self::ALLOWED)) {
            $this->cleanChildren($node);
            $this->unwrap($node);

            return;
        }

        $this->cleanAttributes($node, $tag);

        if ($tag === 'a' && ! $node->hasAttribute('href')) {
            $this->cleanChildren($node);
            $this->unwrap($node);

            return;
        }

        if ($tag === 'img' && ! $node->hasAttribute('src')) {
            $node->parentNode?->removeChild($node);

            return;
        }

        $this->cleanChildren($node);
    }

    private function cleanAttributes(DOMElement $element, string $tag): void
    {
        $allowed = self::ALLOWED[$tag];

        foreach (iterator_to_array($element->attributes) as $attribute) {
            $name = strtolower($attribute->nodeName);
            $value = (string) $attribute->nodeValue;
            $element->removeAttribute($attribute->nodeName);

            if (! in_array($name, $allowed, true)) {
                continue;
            }

            $clean = $this->cleanAttributeValue($tag, $name, $value);

            if ($clean !== null) {
                $element->setAttribute($name, $clean);
            }
        }

        if ($tag === 'a' && $element->hasAttribute('href') && $this->isExternal($element->getAttribute('href'))) {
            $element->setAttribute('rel', 'noopener noreferrer nofollow');
        }

        if ($tag === 'img' && ! $element->hasAttribute('alt')) {
            $element->setAttribute('alt', '');
        }

        // Callouts sit inside the article's <main>: a note, not a nested complementary landmark (WCAG 1.3.1).
        if ($tag === 'aside') {
            $element->setAttribute('role', 'note');
        }
    }

    private function cleanAttributeValue(string $tag, string $name, string $value): ?string
    {
        $value = trim($value);

        return match ($name) {
            'href' => $this->safeUrl($value, allowFragment: true),
            'src' => $this->safeUrl($value, allowFragment: false),
            'dir' => in_array(strtolower($value), ['ltr', 'rtl', 'auto'], true) ? strtolower($value) : null,
            'width', 'height' => preg_match('/^\d{1,4}$/', $value) === 1 ? $value : null,
            'colspan', 'rowspan' => preg_match('/^[1-9]\d?$/', $value) === 1 ? $value : null,
            'start' => preg_match('/^\d{1,5}$/', $value) === 1 ? $value : null,
            'scope' => in_array($value, ['row', 'col'], true) ? $value : null,
            'data-media-id' => preg_match('/^\d{1,12}$/', $value) === 1 ? $value : null,
            'data-callout' => in_array($value, self::CALLOUT_TYPES, true) ? $value : 'info',
            'class' => $tag === 'code' && preg_match('/^language-[a-z0-9+#-]{1,30}$/', $value) === 1 ? $value : null,
            'alt', 'title' => mb_substr(preg_replace('/[\x00-\x1F\x7F]/u', '', $value) ?? '', 0, 300),
            default => null,
        };
    }

    /**
     * Accept https URLs, site-relative paths ("/blog/…") and, for links, in-page fragments. Everything else
     * (javascript:, data:, vbscript:, protocol-relative "//host", http:, mailto:…) is rejected.
     */
    public function safeUrl(string $url, bool $allowFragment): ?string
    {
        // Browsers ignore control characters and whitespace inside schemes ("java\tscript:"), so compare without them.
        $normalized = preg_replace('/[\x00-\x20\x7F\x{00A0}\x{1680}\x{2000}-\x{200F}\x{2028}\x{2029}\x{202F}\x{205F}\x{3000}\x{FEFF}]+/u', '', $url);

        if ($normalized === null || $normalized === '' || mb_strlen($normalized) > 2000 || str_contains($normalized, '\\')) {
            return null;
        }

        if (str_starts_with($normalized, '#')) {
            return $allowFragment && preg_match('/^#[\p{L}\p{N}_-]{1,120}$/u', $normalized) === 1 ? $normalized : null;
        }

        if (str_starts_with($normalized, '//')) {
            return null;
        }

        if (str_starts_with($normalized, '/')) {
            return $normalized;
        }

        if (preg_match('/^https:\/\/[^\/?#@\s]+/i', $normalized) === 1 && filter_var($this->asciiUrl($normalized), FILTER_VALIDATE_URL) !== false) {
            return $normalized;
        }

        return null;
    }

    public function isExternal(string $url): bool
    {
        if (! str_starts_with(strtolower($url), 'https://')) {
            return false;
        }

        return strtolower((string) parse_url($url, PHP_URL_HOST)) !== strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
    }

    /**
     * Rebuild an allowed video iframe with fixed, safe attributes, or drop it.
     */
    private function replaceEmbed(DOMElement $iframe): void
    {
        $src = $this->embedUrl((string) $iframe->getAttribute('src'));
        $parent = $iframe->parentNode;

        if ($src === null || $parent === null) {
            $parent?->removeChild($iframe);

            return;
        }

        $document = $iframe->ownerDocument;
        $embed = $document->createElement('iframe');
        $embed->setAttribute('src', $src);
        $embed->setAttribute('title', mb_substr(trim((string) $iframe->getAttribute('title')), 0, 200) ?: 'Video');
        $embed->setAttribute('loading', 'lazy');
        $embed->setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
        $embed->setAttribute('sandbox', 'allow-scripts allow-same-origin allow-presentation allow-popups');
        $embed->setAttribute('allow', 'encrypted-media; picture-in-picture; fullscreen');
        $embed->setAttribute('allowfullscreen', '');
        $parent->replaceChild($embed, $iframe);
    }

    /**
     * Privacy-enhanced embed URL for YouTube (youtube-nocookie.com) or Vimeo (dnt=1), else null.
     */
    public function embedUrl(string $url): ?string
    {
        $url = trim($url);

        if (preg_match('#^https://(?:www\.)?(?:youtube\.com|youtube-nocookie\.com)/embed/([A-Za-z0-9_-]{6,20})(?:[?\#].*)?$#', $url, $matches) === 1) {
            return 'https://www.youtube-nocookie.com/embed/'.$matches[1];
        }

        if (preg_match('#^https://player\.vimeo\.com/video/(\d{4,12})(?:[?\#].*)?$#', $url, $matches) === 1) {
            return 'https://player.vimeo.com/video/'.$matches[1].'?dnt=1';
        }

        return null;
    }

    private function rename(DOMElement $element, string $tag): DOMElement
    {
        $replacement = $element->ownerDocument->createElement($tag);

        foreach (iterator_to_array($element->attributes) as $attribute) {
            $replacement->setAttribute($attribute->nodeName, (string) $attribute->nodeValue);
        }

        while ($element->firstChild) {
            $replacement->appendChild($element->firstChild);
        }

        $element->parentNode?->replaceChild($replacement, $element);

        return $replacement;
    }

    private function unwrap(DOMElement $element): void
    {
        $parent = $element->parentNode;

        if ($parent === null) {
            return;
        }

        while ($element->firstChild) {
            $parent->insertBefore($element->firstChild, $element);
        }

        $parent->removeChild($element);
    }

    private function asciiUrl(string $url): string
    {
        return preg_replace_callback('/[^\x21-\x7E]/u', fn (array $match): string => rawurlencode($match[0]), $url) ?? $url;
    }
}
