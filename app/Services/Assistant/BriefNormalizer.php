<?php

namespace App\Services\Assistant;

use App\Enums\BudgetRange;
use App\Enums\ContactMethod;
use App\Enums\ProjectPlatform;
use App\Enums\ProjectTimeline;
use App\Models\Language;
use App\Models\Service;
use App\Models\Technology;
use App\Services\Countries;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Turns untrusted brief data (model structured output or guided answers) into the canonical brief: every
 * field is checked against the same enums and rules as the quote form; anything invalid is dropped.
 *
 * Canonical brief keys: project_type, description, platforms, features, goals, existing_system,
 * existing_url, services (ids), technologies (ids), technology_notes, timeline, budget_range, name, email,
 * phone, company, country, preferred_contact, preferred_locale.
 */
class BriefNormalizer
{
    public const PHONE_PATTERN = '/^\+?[0-9 ()-]{6,30}$/';

    /**
     * @var array<int, string>|null
     */
    private ?array $technologyNames = null;

    /**
     * JSON schema for the model's structured reply (strict: every property required, null/[] when unknown).
     *
     * @return array<string, mixed>
     */
    public function schema(): array
    {
        $nullableString = ['type' => ['string', 'null']];
        $enum = fn (array $values): array => ['type' => ['string', 'null'], 'enum' => [...$values, null]];
        $strings = ['type' => 'array', 'items' => ['type' => 'string']];

        $brief = [
            'project_type' => $nullableString,
            'description' => $nullableString,
            'platforms' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => array_column(ProjectPlatform::cases(), 'value')]],
            'features' => $strings,
            'goals' => $nullableString,
            'existing_system' => $nullableString,
            'services' => ['type' => 'array', 'items' => ['type' => 'integer']],
            'technologies' => $strings,
            'timeline' => $enum(array_column(ProjectTimeline::cases(), 'value')),
            'budget_range' => $enum(array_column(BudgetRange::cases(), 'value')),
            'name' => $nullableString,
            'email' => $nullableString,
            'phone' => $nullableString,
            'company' => $nullableString,
            'country' => $nullableString,
            'preferred_contact' => $enum(array_column(ContactMethod::cases(), 'value')),
            'preferred_locale' => $enum(Language::active()->keys()->all()),
        ];

        return [
            'type' => 'object',
            'properties' => [
                'reply' => ['type' => 'string'],
                'quick_replies' => $strings,
                'ready_for_summary' => ['type' => 'boolean'],
                'brief' => [
                    'type' => 'object',
                    'properties' => $brief,
                    'required' => array_keys($brief),
                    'additionalProperties' => false,
                ],
            ],
            'required' => ['reply', 'quick_replies', 'ready_for_summary', 'brief'],
            'additionalProperties' => false,
        ];
    }

    /**
     * Valid fields only; unknown keys and invalid values are dropped.
     *
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    public function normalize(array $raw): array
    {
        $brief = [
            'project_type' => $this->line($raw['project_type'] ?? null, 120),
            'description' => $this->paragraph($raw['description'] ?? null, 2000),
            'platforms' => $this->enumList($raw['platforms'] ?? null, ProjectPlatform::class),
            'features' => $this->features($raw['features'] ?? null),
            'goals' => $this->paragraph($raw['goals'] ?? null, 1000),
            'existing_system' => $this->line($raw['existing_system'] ?? null, 500),
            'services' => $this->services($raw['services'] ?? null),
            'timeline' => ProjectTimeline::tryFrom((string) ($raw['timeline'] ?? ''))?->value,
            'budget_range' => BudgetRange::tryFrom((string) ($raw['budget_range'] ?? ''))?->value,
            'name' => $this->line($raw['name'] ?? null, 120),
            'email' => $this->email($raw['email'] ?? null),
            'phone' => $this->phone($raw['phone'] ?? null),
            'company' => $this->line($raw['company'] ?? null, 160),
            'country' => $this->country($raw['country'] ?? null),
            'preferred_contact' => ContactMethod::tryFrom((string) ($raw['preferred_contact'] ?? ''))?->value,
            'preferred_locale' => Language::active()->has((string) ($raw['preferred_locale'] ?? '')) ? (string) $raw['preferred_locale'] : null,
        ];

        $url = $this->url($raw['existing_url'] ?? $brief['existing_system']);
        $brief['existing_url'] = $url;

        [$brief['technologies'], $brief['technology_notes']] = $this->technologies($raw['technologies'] ?? null);

        return array_filter($brief, fn ($value): bool => $value !== null && $value !== []);
    }

    /**
     * Newer values win; null/empty never erase what the visitor already said.
     *
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $updates  already normalized
     * @return array<string, mixed>
     */
    public function merge(array $current, array $updates): array
    {
        foreach ($updates as $key => $value) {
            if ($value !== null && $value !== '' && $value !== []) {
                $current[$key] = $value;
            }
        }

        return $current;
    }

    public static function cleanText(mixed $value, int $limit): ?string
    {
        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        $text = Str::of(strip_tags((string) $value))->squish()->limit($limit, '')->trim()->toString();

        return $text === '' ? null : $text;
    }

    private function line(mixed $value, int $limit): ?string
    {
        return self::cleanText($value, $limit);
    }

    private function paragraph(mixed $value, int $limit): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $lines = collect(preg_split('/\R/u', strip_tags($value)) ?: [])
            ->map(fn (string $line): string => Str::squish($line));
        $text = trim((string) preg_replace("/\n{3,}/", "\n\n", $lines->implode("\n")));
        $text = Str::limit($text, $limit, '');

        return $text === '' ? null : $text;
    }

    /**
     * @param  class-string<\BackedEnum>  $enum
     * @return list<string>
     */
    private function enumList(mixed $values, string $enum): array
    {
        return collect(is_array($values) ? $values : [$values])
            ->map(fn ($value): ?string => is_string($value) ? $enum::tryFrom($value)?->value : null)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function features(mixed $value): array
    {
        $items = is_string($value) ? preg_split('/\R|;/u', $value) : $value;

        return collect(is_array($items) ? $items : [])
            ->map(fn ($item): ?string => self::cleanText(is_string($item) ? ltrim($item, "-•* \t") : null, 200))
            ->filter()
            ->unique()
            ->take(15)
            ->values()
            ->all();
    }

    /**
     * @return list<int>
     */
    private function services(mixed $value): array
    {
        $ids = collect(is_array($value) ? $value : [])
            ->filter(fn ($id): bool => is_int($id) || (is_string($id) && ctype_digit($id)))
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->take(10);

        if ($ids->isEmpty()) {
            return [];
        }

        return Service::query()->published()->whereKey($ids->all())->orderBy('id')->pluck('id')->map(fn ($id): int => (int) $id)->all();
    }

    /**
     * Technology names matched against the visible catalog (any language); the rest kept as short notes.
     *
     * @return array{0: list<int>, 1: list<string>}
     */
    private function technologies(mixed $value): array
    {
        $names = collect(is_array($value) ? $value : [])
            ->map(fn ($name): ?string => self::cleanText($name, 60))
            ->filter()
            ->unique()
            ->take(20);

        if ($names->isEmpty()) {
            return [[], []];
        }

        $catalog = $this->technologyNames ??= Technology::query()->where('is_visible', true)->with('translations')->get()
            ->flatMap(fn (Technology $technology) => $technology->translations->mapWithKeys(fn ($translation): array => [mb_strtolower((string) $translation->name) => $technology->getKey()]))
            ->all();

        $ids = [];
        $notes = [];

        foreach ($names as $name) {
            $id = $catalog[mb_strtolower($name)] ?? null;
            $id !== null ? $ids[] = (int) $id : $notes[] = $name;
        }

        return [array_values(array_unique($ids)), array_slice($notes, 0, 10)];
    }

    private function email(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $email = mb_strtolower(trim($value));

        return Validator::make(['email' => $email], ['email' => ['required', 'email:rfc', 'max:255']])->passes() ? $email : null;
    }

    private function phone(mixed $value): ?string
    {
        $phone = is_string($value) ? trim($value) : '';

        return preg_match(self::PHONE_PATTERN, $phone) === 1 ? $phone : null;
    }

    private function country(mixed $value): ?string
    {
        $code = is_string($value) ? mb_strtoupper(trim($value)) : '';

        return in_array($code, Countries::CODES, true) ? $code : null;
    }

    private function url(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $url = trim($value);

        return mb_strlen($url) <= 500
            && preg_match('#^https?://#i', $url) === 1
            && filter_var($url, FILTER_VALIDATE_URL) !== false ? $url : null;
    }
}
