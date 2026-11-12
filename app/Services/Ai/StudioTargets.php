<?php

namespace App\Services\Ai;

use App\Contracts\Approvable;
use App\Enums\PromptPurpose;
use App\Models\Page;
use App\Models\PortfolioProject;
use App\Models\Post;
use App\Models\Product;
use App\Models\Service;
use App\Services\Blog\PostRevisions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Website items the Content Studio can write for (keyed by morph alias). Every one implements Approvable, so a
 * generation for them can only become a pending change request.
 */
class StudioTargets
{
    public const SEO_FIELDS = ['seo_title', 'seo_description'];

    /**
     * @var array<string, array{model: class-string<Model&Approvable>, label: string, title: string, content: list<string>, html: list<string>}>
     */
    private const TYPES = [
        'page' => ['model' => Page::class, 'label' => 'Pages', 'title' => 'title', 'content' => ['title', 'body'], 'html' => []],
        'service' => ['model' => Service::class, 'label' => 'Services', 'title' => 'title', 'content' => ['title', 'summary', 'body'], 'html' => []],
        'product' => ['model' => Product::class, 'label' => 'Software products', 'title' => 'name', 'content' => ['name', 'tagline', 'description'], 'html' => []],
        'portfolio_project' => ['model' => PortfolioProject::class, 'label' => 'Portfolio', 'title' => 'title', 'content' => ['title', 'summary', 'challenge', 'solution', 'results'], 'html' => []],
        'post' => ['model' => Post::class, 'label' => 'Blog', 'title' => 'title', 'content' => ['title', 'excerpt', 'body'], 'html' => ['body']],
    ];

    /**
     * Longest text accepted per field (generated or edited).
     */
    private const MAX_LENGTHS = [
        'seo_title' => 70, 'seo_description' => 170, 'title' => 160, 'name' => 160, 'tagline' => 255, 'excerpt' => 500,
        'summary' => 1000, 'subject' => 160, 'preheader' => 200, 'headline' => 120, 'call_to_action' => 120,
        'hashtags' => 300, 'text' => 3000, 'description' => 20000,
    ];

    /**
     * @return array<string, string> type => translated label
     */
    public function options(): array
    {
        return collect(self::TYPES)->map(fn (array $type): string => __($type['label']))->all();
    }

    public function has(string $type): bool
    {
        return isset(self::TYPES[$type]);
    }

    public function find(string $type, int|string $id): (Model&Approvable)|null
    {
        if (! $this->has($type)) {
            return null;
        }

        /** @var (Model&Approvable)|null */
        return self::TYPES[$type]['model']::query()->with('translations')->find($id);
    }

    /**
     * Recent items of a type for the target picker: id => title (default language first).
     *
     * @return Collection<int, string>
     */
    public function items(string $type): Collection
    {
        if (! $this->has($type)) {
            return collect();
        }

        $titleField = self::TYPES[$type]['title'];

        return self::TYPES[$type]['model']::query()->with('translations')->latest('id')->limit(200)->get()
            ->mapWithKeys(fn (Model $item): array => [$item->getKey() => $this->title($item, $titleField)]);
    }

    public function typeOf(Model $target): string
    {
        return $target->getMorphClass();
    }

    public function label(string $type): string
    {
        return __(self::TYPES[$type]['label'] ?? Str::headline($type));
    }

    public function titleOf(Model $target): string
    {
        return $this->title($target, self::TYPES[$this->typeOf($target)]['title'] ?? 'title');
    }

    /**
     * Fields a purpose writes for a target type (or the purpose's fixed fields when there is no target).
     *
     * @return list<string>
     */
    public function fieldsFor(PromptPurpose $purpose, ?string $type): array
    {
        if ($type === null || ! $this->has($type)) {
            return $purpose->outputFields();
        }

        $content = self::TYPES[$type]['content'];

        return match ($purpose) {
            PromptPurpose::SeoMeta => self::SEO_FIELDS,
            PromptPurpose::Translation => [...$content, ...self::SEO_FIELDS],
            default => $content,
        };
    }

    /**
     * Fields stored as sanitized HTML for this type; the studio always works in Markdown and converts on proposal.
     *
     * @return list<string>
     */
    public function htmlFields(?string $type): array
    {
        return $type !== null ? (self::TYPES[$type]['html'] ?? []) : [];
    }

    public function maxLength(string $field): int
    {
        return self::MAX_LENGTHS[$field] ?? 20000;
    }

    /**
     * Current text of the given fields per locale, as the studio shows it (HTML turned into plain lines).
     *
     * @param  list<string>  $fields
     * @param  list<string>  $locales
     * @return array<string, array<string, string>>
     */
    public function currentContent(Model $target, array $fields, array $locales): array
    {
        $html = $this->htmlFields($this->typeOf($target));
        $content = [];

        foreach ($locales as $locale) {
            $translation = $target->translations->firstWhere('locale', $locale);

            foreach ($fields as $field) {
                $value = (string) ($translation?->{$field} ?? '');
                $content[$locale][$field] = in_array($field, $html, true) ? self::htmlToText($value) : $value;
            }
        }

        return $content;
    }

    public static function fieldLabel(string $field): string
    {
        return match ($field) {
            'seo_title' => __('SEO title'),
            'seo_description' => __('Meta description'),
            'call_to_action' => __('Call to action'),
            'focus_keyword' => __('Focus keyword'),
            default => __(Str::headline($field)),
        };
    }

    public static function htmlToText(string $html): string
    {
        return implode("\n\n", PostRevisions::htmlToLines($html));
    }

    private function title(Model $item, string $field): string
    {
        $translation = $item->translations->firstWhere('locale', config('app.locale')) ?? $item->translations->first();

        return (string) ($translation?->{$field} ?? '#'.$item->getKey());
    }
}
