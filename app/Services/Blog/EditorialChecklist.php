<?php

namespace App\Services\Blog;

use App\Models\Language;
use App\Models\Post;
use App\Models\PostTranslation;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Editorial checklist. Only the default-language title, slug and excerpt are hard requirements; every other
 * item is a warning that reviewers must acknowledge before approving or publishing.
 */
class EditorialChecklist
{
    public const TITLE_MIN = 20;

    public const TITLE_MAX = 70;

    public const META_MIN = 50;

    public const META_MAX = 160;

    /**
     * Items an editor confirms by hand (they cannot be checked automatically).
     *
     * @return array<string, string>
     */
    public static function manualItems(): array
    {
        return [
            'accurate' => __('Accurate, no fabricated claims'),
            'sources_verified' => __('External sources verified'),
            'original' => __('Original, non-duplicate content'),
            'language_reviewed' => __('Language and tone reviewed'),
        ];
    }

    /**
     * Evaluate the article (translations must be loaded). Manual items come from $manual (item => checked).
     *
     * @param  array<string, bool>  $manual
     * @return list<array{key: string, label: string, passed: bool, required: bool, manual: bool, detail: string|null}>
     */
    public function evaluate(Post $post, array $manual = []): array
    {
        $default = Language::defaultCode();
        $languages = Language::active();
        /** @var Collection<int, PostTranslation> $filled */
        $filled = $post->translations->filter(fn (PostTranslation $translation): bool => filled($translation->title))->values();
        $primary = $filled->firstWhere('locale', $default);
        $failing = fn (callable $check): array => $filled->reject($check)->map(fn (PostTranslation $translation): string => strtoupper($translation->locale))->values()->all();
        $inLocales = fn (array $locales): ?string => $locales === [] ? null : __('Needs attention in: :languages', ['languages' => implode(', ', $locales)]);

        $missingRequired = array_keys(array_filter([
            __('Title') => blank($primary?->title),
            __('URL slug') => blank($primary?->slug),
            __('Excerpt') => blank($primary?->excerpt),
        ]));

        $titleIssues = $failing(fn (PostTranslation $translation): bool => mb_strlen((string) $translation->title) >= self::TITLE_MIN && mb_strlen((string) $translation->title) <= self::TITLE_MAX);
        $metaIssues = $failing(fn (PostTranslation $translation): bool => mb_strlen((string) $translation->seo_description) >= self::META_MIN && mb_strlen((string) $translation->seo_description) <= self::META_MAX);
        $keywordIssues = $failing(fn (PostTranslation $translation): bool => $this->keywordPlaced($translation));
        $altIssues = $failing(fn (PostTranslation $translation): bool => ! preg_match('/<img\b(?![^>]*\balt="[^"]*[^"\s][^"]*")[^>]*>/iu', (string) $translation->body));
        $linkIssues = $failing(fn (PostTranslation $translation): bool => $this->hasInternalLink((string) $translation->body));
        $headingIssues = $failing(fn (PostTranslation $translation): bool => str_contains((string) $translation->body, '<h2'));
        $missingLanguages = $languages->keys()->reject(fn (string $code): bool => $filled->contains('locale', $code))->map(fn (string $code): string => strtoupper($code))->values()->all();
        $hasContent = $filled->isNotEmpty();

        $items = [
            $this->item('required_fields', __('Title, slug and excerpt in :language', ['language' => $languages[$default]['name'] ?? strtoupper($default)]), $missingRequired === [], true,
                $missingRequired === [] ? null : __('Missing: :fields', ['fields' => implode(', ', $missingRequired)])),
            $this->item('title_length', __('Title between :min and :max characters', ['min' => self::TITLE_MIN, 'max' => self::TITLE_MAX]), $hasContent && $titleIssues === [], false, $inLocales($titleIssues)),
            $this->item('meta_description', __('Meta description between :min and :max characters', ['min' => self::META_MIN, 'max' => self::META_MAX]), $hasContent && $metaIssues === [], false, $inLocales($metaIssues)),
            $this->item('focus_keyword', __('Focus keyword in the title and first paragraph'), $hasContent && $keywordIssues === [], false, $inLocales($keywordIssues)),
            $this->item('image_alt', __('Every image has alt text'), $hasContent && $altIssues === [], false, $inLocales($altIssues)),
            $this->item('internal_link', __('At least one internal link'), $hasContent && $linkIssues === [], false, $inLocales($linkIssues)),
            $this->item('heading_structure', __('At least one H2 heading'), $hasContent && $headingIssues === [], false, $inLocales($headingIssues)),
            $this->item('languages', __('All languages filled, or marked single-language'), $missingLanguages === [] || $post->is_single_language, false,
                $missingLanguages === [] || $post->is_single_language ? null : __('Missing: :languages', ['languages' => implode(', ', $missingLanguages)])),
        ];

        foreach (self::manualItems() as $key => $label) {
            $items[] = $this->item($key, $label, (bool) ($manual[$key] ?? false), false, null, true);
        }

        return $items;
    }

    /**
     * Manual confirmations stored for the article.
     *
     * @return array<string, bool>
     */
    public function storedManual(Post $post): array
    {
        return $post->checklistItems
            ->where('is_manual', true)
            ->mapWithKeys(fn ($item): array => [$item->item => (bool) $item->is_passed])
            ->all();
    }

    /**
     * Persist the evaluation. Manual items remember who confirmed them and when.
     *
     * @param  list<array{key: string, label: string, passed: bool, required: bool, manual: bool, detail: string|null}>  $results
     */
    public function store(Post $post, array $results, ?User $user): void
    {
        $existing = $post->checklistItems()->get()->keyBy('item');

        foreach ($results as $result) {
            $previous = $existing->get($result['key']);
            $newlyChecked = $result['manual'] && $result['passed'] && ! ($previous?->is_passed ?? false);

            $post->checklistItems()->updateOrCreate(['item' => $result['key']], [
                'is_manual' => $result['manual'],
                'is_passed' => $result['passed'],
                'detail' => $result['detail'] !== null ? mb_substr($result['detail'], 0, 300) : null,
                'checked_by' => $result['manual'] ? ($result['passed'] ? ($newlyChecked ? $user?->getKey() : $previous?->checked_by) : null) : null,
                'checked_at' => $result['manual'] ? ($result['passed'] ? ($newlyChecked ? now() : $previous?->checked_at) : null) : now(),
            ]);
        }

        $post->unsetRelation('checklistItems');
    }

    /**
     * @param  list<array{key: string, label: string, passed: bool, required: bool, manual: bool, detail: string|null}>  $results
     */
    public static function requirementsMet(array $results): bool
    {
        return collect($results)->where('required', true)->every(fn (array $result): bool => $result['passed']);
    }

    /**
     * @param  list<array{key: string, label: string, passed: bool, required: bool, manual: bool, detail: string|null}>  $results
     */
    public static function warningCount(array $results): int
    {
        return collect($results)->where('required', false)->where('passed', false)->count();
    }

    /**
     * @param  list<array{key: string, label: string, passed: bool, required: bool, manual: bool, detail: string|null}>  $results
     */
    public static function score(array $results): int
    {
        return $results === [] ? 0 : (int) round(collect($results)->where('passed', true)->count() / count($results) * 100);
    }

    private function keywordPlaced(PostTranslation $translation): bool
    {
        $keyword = mb_strtolower(trim((string) $translation->focus_keyword));

        if ($keyword === '') {
            return false;
        }

        $firstParagraph = preg_match('/<p\b[^>]*>(.*?)<\/p>/isu', (string) $translation->body, $matches) === 1
            ? html_entity_decode(strip_tags($matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8')
            : '';

        return str_contains(mb_strtolower((string) $translation->title), $keyword)
            && str_contains(mb_strtolower($firstParagraph), $keyword);
    }

    private function hasInternalLink(string $body): bool
    {
        $host = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));

        preg_match_all('/<a\b[^>]*\bhref="([^"]+)"/iu', $body, $matches);

        foreach ($matches[1] as $href) {
            $href = html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8');

            if ((str_starts_with($href, '/') && ! str_starts_with($href, '//')) || ($host !== '' && strtolower((string) parse_url($href, PHP_URL_HOST)) === $host)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{key: string, label: string, passed: bool, required: bool, manual: bool, detail: string|null}
     */
    private function item(string $key, string $label, bool $passed, bool $required, ?string $detail, bool $manual = false): array
    {
        return ['key' => $key, 'label' => $label, 'passed' => $passed, 'required' => $required, 'manual' => $manual, 'detail' => $passed ? null : $detail];
    }
}
