<?php

namespace App\Services;

/**
 * The Design v2 static image set (docs/DESIGN_V2.md §5). JPEG sources live in resources/images/v2; `site-images:build`
 * writes cropped, responsive AVIF + WebP variants and one JPEG fallback per image to public/images/v2, which are
 * committed. File names are deterministic, so pages never read a manifest at runtime.
 */
class SiteImages
{
    /**
     * Candidate variant widths; each image also gets a variant at its own (cropped) width.
     *
     * @var list<int>
     */
    public const WIDTHS = [480, 640, 960, 1280, 1600];

    /** Widest JPEG fallback (the `<img src>` for browsers without AVIF/WebP). */
    public const FALLBACK_MAX_WIDTH = 960;

    public const SOURCE_DIRECTORY = 'resources/images/v2';

    public const PUBLIC_DIRECTORY = 'images/v2';

    /** `sizes` of the home hero image (560px frame from 600px up, full width minus the 20px gutters below). */
    public const HERO_SIZES = '(min-width: 37.5rem) 560px, calc(100vw - 2.5rem)';

    /** Default OpenGraph image (1200×630 crop of the hero). */
    public const OG_IMAGE = 'og-default.jpg';

    /**
     * Fallback covers for project and product cards (§5 fallback cover rule).
     *
     * @var list<string>
     */
    public const PROJECT_COVERS = ['cover-indigo-torus', 'cover-teal-arch', 'cover-violet-orbit'];

    /**
     * Fallback covers for article cards (§4 Card/Article).
     *
     * @var list<string>
     */
    public const ARTICLE_COVERS = ['about-glass-stack', 'process-glass-steps', 'cover-indigo-torus'];

    /**
     * Output size after the centre crop, and the alt text (EN key; Arabic in lang/ar.json). Decorative images have none.
     *
     * @return array<string, array{width: int, height: int, alt: string|null}>
     */
    public static function definitions(): array
    {
        return [
            // 560×540 hero visual.
            'hero-glass-shapes' => ['width' => 929, 'height' => 896, 'alt' => 'Floating glass shapes in indigo and teal'],
            // 11:8 page-hero accent.
            'about-glass-stack' => ['width' => 1200, 'height' => 873, 'alt' => 'Stacked glass layers representing a product being built'],
            'process-glass-steps' => ['width' => 1200, 'height' => 896, 'alt' => 'Glass discs in an ascending sequence, one step at a time'],
            // 16:10 fallback covers.
            'cover-indigo-torus' => ['width' => 1264, 'height' => 790, 'alt' => 'Abstract indigo glass ring'],
            'cover-teal-arch' => ['width' => 1024, 'height' => 640, 'alt' => 'Abstract teal glass arch and steps'],
            'cover-violet-orbit' => ['width' => 1264, 'height' => 790, 'alt' => 'Abstract violet and teal glass orbit'],
            'cta-dark-glow' => ['width' => 1376, 'height' => 768, 'alt' => null],
            'not-found-lost-cube' => ['width' => 1024, 'height' => 1024, 'alt' => 'A single glass cube drifting away from its grid'],
        ];
    }

    /**
     * @return array{width: int, height: int, alt: string|null}
     */
    public static function definition(string $name): array
    {
        return self::definitions()[$name] ?? throw new \InvalidArgumentException("Unknown site image [{$name}].");
    }

    /**
     * @return list<int>
     */
    public static function widths(string $name): array
    {
        $width = self::definition($name)['width'];

        return [...array_values(array_filter(self::WIDTHS, fn (int $candidate): bool => $candidate < $width)), $width];
    }

    public static function fallbackWidth(string $name): int
    {
        return max(array_filter(self::widths($name), fn (int $width): bool => $width <= self::FALLBACK_MAX_WIDTH) ?: [min(self::widths($name))]);
    }

    public static function heightFor(string $name, int $width): int
    {
        $definition = self::definition($name);

        return (int) round($width * $definition['height'] / $definition['width']);
    }

    public static function path(string $name, int $width, string $format): string
    {
        return self::PUBLIC_DIRECTORY."/{$name}-{$width}.{$format}";
    }

    public static function url(string $name, int $width, string $format): string
    {
        return asset(self::path($name, $width, $format));
    }

    public static function srcset(string $name, string $format): string
    {
        return collect(self::widths($name))->map(fn (int $width): string => self::url($name, $width, $format).' '.$width.'w')->implode(', ');
    }

    /**
     * Localized alt text, or an empty string for decorative images.
     */
    public static function alt(string $name): string
    {
        $alt = self::definition($name)['alt'];

        return $alt === null ? '' : __($alt);
    }

    /**
     * Stable fallback cover for an item without an uploaded image: `$covers[$id % count]`.
     *
     * @param  list<string>  $covers
     */
    public static function coverFor(int $id, array $covers = self::PROJECT_COVERS): string
    {
        return $covers[$id % count($covers)];
    }

    /**
     * Every generated file (relative to public/), for the build command and its test.
     *
     * @return list<string>
     */
    public static function expectedFiles(): array
    {
        $files = [self::PUBLIC_DIRECTORY.'/'.self::OG_IMAGE];

        foreach (array_keys(self::definitions()) as $name) {
            foreach (self::widths($name) as $width) {
                $files[] = self::path($name, $width, 'avif');
                $files[] = self::path($name, $width, 'webp');
            }

            $files[] = self::path($name, self::fallbackWidth($name), 'jpg');
        }

        return $files;
    }
}
