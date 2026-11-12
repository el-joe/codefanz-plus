<?php

namespace App\Services\Seo;

use App\Services\ContactChannels;
use App\Services\Settings;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * Builds schema.org JSON-LD from data that is visible on the page (and from Settings for the organization), and
 * renders it safely: JSON_HEX_* flags turn <, >, &, ' and " into \u escapes, so no value can close the script
 * element or inject markup. Empty values are dropped rather than emitted as placeholders.
 */
class StructuredData
{
    public const JSON_FLAGS = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;

    public function __construct(private Settings $settings) {}

    /**
     * @return array<string, mixed>
     */
    public function organization(?string $locale = null): array
    {
        $locale ??= app()->getLocale();
        $name = ($this->settings->get('general', 'company_name') ?? [])[$locale] ?? 'CodeFanz';
        $whatsapp = $this->settings->get('contact', 'whatsapp');

        return $this->clean([
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            '@id' => route('home', ['locale' => $locale]).'#organization',
            'name' => $name,
            'url' => route('home', ['locale' => $locale]),
            'email' => $this->settings->get('contact', 'email'),
            'telephone' => $this->settings->get('contact', 'phone'),
            'address' => ($this->settings->get('contact', 'address') ?? [])[$locale] ?? null,
            'sameAs' => $whatsapp ? [ContactChannels::whatsappUrl($whatsapp)] : null,
        ]);
    }

    /**
     * WebSite with a SearchAction pointing at the site search.
     *
     * @return array<string, mixed>
     */
    public function website(?string $locale = null): array
    {
        $locale ??= app()->getLocale();

        return $this->clean([
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            '@id' => route('home', ['locale' => $locale]).'#website',
            'name' => ($this->settings->get('general', 'company_name') ?? [])[$locale] ?? 'CodeFanz',
            'url' => route('home', ['locale' => $locale]),
            'inLanguage' => $locale,
            'publisher' => ['@id' => route('home', ['locale' => $locale]).'#organization'],
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => ['@type' => 'EntryPoint', 'urlTemplate' => route('search', ['locale' => $locale]).'?q={search_term_string}'],
                'query-input' => 'required name=search_term_string',
            ],
        ]);
    }

    /**
     * @param  array<string, string|null>  $crumbs  label => url (null = the current page)
     * @return array<string, mixed>
     */
    public function breadcrumbs(array $crumbs, ?string $currentUrl = null): array
    {
        $position = 0;
        $items = [];

        foreach ($crumbs as $label => $url) {
            $items[] = ['@type' => 'ListItem', 'position' => ++$position, 'name' => (string) $label, 'item' => $url ?? $currentUrl ?? url()->current()];
        }

        return ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $items];
    }

    /**
     * @return array<string, mixed>
     */
    public function service(string $name, ?string $description, string $url, ?string $image = null, ?string $category = null): array
    {
        return $this->clean([
            '@context' => 'https://schema.org',
            '@type' => 'Service',
            'name' => $name,
            'description' => $description ? Str::limit($description, 300) : null,
            'url' => $url,
            'image' => $image,
            'serviceType' => $category,
            'provider' => ['@id' => route('home', ['locale' => app()->getLocale()]).'#organization'],
            'areaServed' => 'Worldwide',
        ]);
    }

    /**
     * A software product. Prices are never stored, so no `offers` are emitted.
     *
     * @return array<string, mixed>
     */
    public function product(string $name, ?string $description, string $url, ?string $image = null, ?string $version = null): array
    {
        return $this->clean([
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $name,
            'description' => $description ? Str::limit($description, 300) : null,
            'url' => $url,
            'image' => $image,
            'category' => 'Software',
            'version' => $version,
            'brand' => ['@id' => route('home', ['locale' => app()->getLocale()]).'#organization'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function script(array $data): HtmlString
    {
        return new HtmlString('<script type="application/ld+json">'.json_encode($data, self::JSON_FLAGS).'</script>');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function clean(array $data): array
    {
        return array_filter($data, fn ($value): bool => $value !== null && $value !== '' && $value !== []);
    }
}
