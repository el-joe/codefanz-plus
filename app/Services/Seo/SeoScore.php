<?php

namespace App\Services\Seo;

/**
 * A simple, transparent on-page score (0–100) from fixed rules. It measures completeness of the metadata only and
 * does not guarantee rankings.
 */
class SeoScore
{
    public const TITLE_MIN = 30;

    public const TITLE_MAX = 60;

    public const DESCRIPTION_MIN = 70;

    public const DESCRIPTION_MAX = 160;

    /**
     * @param  array{title?: ?string, description?: ?string, focus_keyword?: ?string, indexable?: bool}  $fields
     * @return array{score: int, checks: list<array{label: string, passed: bool, points: int}>}
     */
    public function evaluate(array $fields): array
    {
        $title = trim((string) ($fields['title'] ?? ''));
        $description = trim((string) ($fields['description'] ?? ''));
        $keyword = mb_strtolower(trim((string) ($fields['focus_keyword'] ?? '')));
        $titleLength = mb_strlen($title);
        $descriptionLength = mb_strlen($description);

        $checks = [
            [__('Has a title'), $title !== '', 15],
            [__('Title is :min–:max characters', ['min' => self::TITLE_MIN, 'max' => self::TITLE_MAX]), $titleLength >= self::TITLE_MIN && $titleLength <= self::TITLE_MAX, 15],
            [__('Has a meta description'), $description !== '', 15],
            [__('Description is :min–:max characters', ['min' => self::DESCRIPTION_MIN, 'max' => self::DESCRIPTION_MAX]), $descriptionLength >= self::DESCRIPTION_MIN && $descriptionLength <= self::DESCRIPTION_MAX, 15],
            [__('Has a focus keyword'), $keyword !== '', 10],
            [__('Focus keyword appears in the title'), $keyword !== '' && str_contains(mb_strtolower($title), $keyword), 10],
            [__('Focus keyword appears in the description'), $keyword !== '' && str_contains(mb_strtolower($description), $keyword), 10],
            [__('Page can be indexed'), (bool) ($fields['indexable'] ?? true), 10],
        ];

        $checks = array_map(fn (array $check): array => ['label' => $check[0], 'passed' => $check[1], 'points' => $check[2]], $checks);

        return [
            'score' => array_sum(array_map(fn (array $check): int => $check['passed'] ? $check['points'] : 0, $checks)),
            'checks' => $checks,
        ];
    }
}
