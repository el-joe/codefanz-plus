<?php

namespace App\Services\Seo;

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Extracts the SEO-relevant facts from one HTML document for the audit crawler.
 */
class HtmlInspector
{
    /**
     * @return array{title: ?string, description: ?string, robots: ?string, canonical: ?string, hreflang: array<string, string>, h1: int, images: list<array{src: string, has_alt: bool, width: ?int, avif: bool}>, links: list<string>, insecure: list<string>}
     */
    public function inspect(string $html, string $pageUrl): array
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new DOMXPath($document);

        $hreflang = [];

        foreach ($xpath->query('//link[@rel="alternate"][@hreflang]') as $link) {
            /** @var DOMElement $link */
            $hreflang[$link->getAttribute('hreflang')] = $this->absolute($link->getAttribute('href'), $pageUrl);
        }

        $images = [];

        foreach ($xpath->query('//img') as $image) {
            /** @var DOMElement $image */
            // libxml's HTML4 parser may nest <img> inside a non-void <source>, so look for the enclosing <picture>.
            $picture = $xpath->query('ancestor::picture[1]', $image)->item(0);
            $avif = $picture instanceof DOMElement && $xpath->query('.//source[@type="image/avif"]', $picture)->length > 0;
            $width = $image->getAttribute('width');

            $images[] = [
                'src' => $this->absolute($image->getAttribute('src'), $pageUrl),
                'has_alt' => $image->hasAttribute('alt'),
                'width' => ctype_digit($width) ? (int) $width : null,
                'avif' => $avif,
            ];
        }

        $links = [];

        foreach ($xpath->query('//a[@href]') as $anchor) {
            /** @var DOMElement $anchor */
            $href = trim($anchor->getAttribute('href'));

            if ($href === '' || str_starts_with($href, '#') || preg_match('#^(mailto|tel|javascript|data|sms|whatsapp):#i', $href) === 1) {
                continue;
            }

            $links[] = $this->absolute($href, $pageUrl);
        }

        $insecure = [];

        foreach ($xpath->query('//img[@src] | //script[@src] | //iframe[@src] | //source[@src] | //video[@src] | //audio[@src] | //link[@rel="stylesheet"][@href]') as $resource) {
            /** @var DOMElement $resource */
            $value = $resource->getAttribute($resource->hasAttribute('src') ? 'src' : 'href');

            if (str_starts_with(strtolower($value), 'http://')) {
                $insecure[] = $value;
            }
        }

        $meta = fn (string $name): ?string => ($node = $xpath->query('//meta[@name="'.$name.'"]')->item(0)) instanceof DOMElement
            ? trim($node->getAttribute('content'))
            : null;
        $canonical = $xpath->query('//link[@rel="canonical"]')->item(0);
        $title = $xpath->query('//title')->item(0);

        return [
            'title' => $title ? trim((string) preg_replace('/\s+/u', ' ', $title->textContent)) : null,
            'description' => $meta('description'),
            'robots' => $meta('robots'),
            'canonical' => $canonical instanceof DOMElement ? $this->absolute($canonical->getAttribute('href'), $pageUrl) : null,
            'hreflang' => $hreflang,
            'h1' => $xpath->query('//h1')->length,
            'images' => $images,
            'links' => array_values(array_unique($links)),
            'insecure' => array_values(array_unique($insecure)),
        ];
    }

    /**
     * Resolves a (possibly relative) URL against the page URL and drops the fragment.
     */
    public function absolute(string $url, string $base): string
    {
        $url = trim((string) strtok($url, '#'));

        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $url) === 1) {
            return $url;
        }

        $scheme = (string) parse_url($base, PHP_URL_SCHEME);
        $origin = $scheme.'://'.parse_url($base, PHP_URL_HOST).(($port = parse_url($base, PHP_URL_PORT)) ? ':'.$port : '');

        if (str_starts_with($url, '//')) {
            return $scheme.':'.$url;
        }

        if (str_starts_with($url, '/')) {
            return $origin.$url;
        }

        $path = (string) parse_url($base, PHP_URL_PATH);

        return $origin.rtrim(substr($path, 0, (int) strrpos($path, '/') + 1), '/').'/'.$url;
    }
}
