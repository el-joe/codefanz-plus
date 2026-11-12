<?php

namespace App\Services\Assistant;

use App\Enums\BudgetRange;
use App\Enums\ContactMethod;
use App\Enums\ProjectPlatform;
use App\Enums\ProjectTimeline;
use App\Models\Language;
use App\Models\Service;

/**
 * What the brief panel shows: the six details (budget optional), whether the project part is complete,
 * the confirm-summary rows and the guided questionnaire's next step.
 */
class BriefProgress
{
    /**
     * Guided questionnaire steps, in order. Contact details are collected on the confirm card.
     *
     * @var list<string>
     */
    public const GUIDED_STEPS = ['project', 'platforms', 'features', 'timeline', 'budget'];

    /**
     * @param  array<string, mixed>  $brief
     * @return list<array{key: string, label: string, done: bool, optional: bool}>
     */
    public function items(array $brief): array
    {
        return [
            ['key' => 'project', 'label' => __('Project type'), 'done' => $this->has($brief, 'project'), 'optional' => false],
            ['key' => 'platforms', 'label' => __('Platforms'), 'done' => $this->has($brief, 'platforms'), 'optional' => false],
            ['key' => 'features', 'label' => __('Key features'), 'done' => $this->has($brief, 'features'), 'optional' => false],
            ['key' => 'timeline', 'label' => __('Timeline'), 'done' => $this->has($brief, 'timeline'), 'optional' => false],
            ['key' => 'budget', 'label' => __('Budget (optional)'), 'done' => $this->has($brief, 'budget'), 'optional' => true],
            ['key' => 'contact', 'label' => __('Contact details'), 'done' => $this->has($brief, 'contact'), 'optional' => false],
        ];
    }

    /**
     * @param  array<string, mixed>  $brief
     * @return array{collected: int, total: int, items: list<array{key: string, label: string, done: bool, optional: bool}>}
     */
    public function progress(array $brief): array
    {
        $items = $this->items($brief);

        return ['collected' => count(array_filter($items, fn (array $item): bool => $item['done'])), 'total' => count($items), 'items' => $items];
    }

    /**
     * The project part is complete: the confirm card (with the contact form) can be shown.
     *
     * @param  array<string, mixed>  $brief
     */
    public function isReady(array $brief): bool
    {
        foreach (['project', 'platforms', 'features', 'timeline'] as $key) {
            if (! $this->has($brief, $key)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Next guided step, skipping what the brief already has (e.g. after switching from AI mid-conversation).
     *
     * @param  array<string, mixed>  $brief
     * @param  list<string>  $answered
     */
    public function nextGuidedStep(array $brief, array $answered): ?string
    {
        foreach (self::GUIDED_STEPS as $step) {
            if (! in_array($step, $answered, true) && ! $this->has($brief, $step)) {
                return $step;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $brief
     */
    public function has(array $brief, string $key): bool
    {
        return match ($key) {
            'project' => filled($brief['project_type'] ?? null) || filled($brief['description'] ?? null),
            'platforms' => ! empty($brief['platforms']),
            'features' => ! empty($brief['features']),
            'timeline' => filled($brief['timeline'] ?? null),
            'budget' => filled($brief['budget_range'] ?? null),
            'contact' => filled($brief['name'] ?? null) && filled($brief['email'] ?? null)
                && (! in_array($brief['preferred_contact'] ?? 'email', ['whatsapp', 'phone'], true) || filled($brief['phone'] ?? null)),
            default => false,
        };
    }

    /**
     * Rows for the confirm-summary card, in the visitor's language.
     *
     * @param  array<string, mixed>  $brief
     * @return list<array{label: string, value: string}>
     */
    public function summary(array $brief): array
    {
        $services = empty($brief['services']) ? null : Service::query()->whereKey($brief['services'])->with('translations')->get()
            ->map(fn (Service $service): ?string => $service->translation()?->title)->filter()->implode(', ');

        $rows = [
            __('Type') => $brief['project_type'] ?? null,
            __('Description') => $brief['description'] ?? null,
            __('Services') => $services,
            __('Platforms') => collect($brief['platforms'] ?? [])->map(fn (string $platform) => ProjectPlatform::tryFrom($platform)?->label())->filter()->implode(', '),
            __('Key features') => implode(', ', $brief['features'] ?? []),
            __('Existing system') => $brief['existing_system'] ?? null,
            __('Timeline') => ProjectTimeline::tryFrom((string) ($brief['timeline'] ?? ''))?->label(),
            __('Budget') => BudgetRange::tryFrom((string) ($brief['budget_range'] ?? ''))?->label(),
        ];

        return collect($rows)
            ->filter(fn (?string $value): bool => filled($value))
            ->map(fn (string $value, string $label): array => ['label' => $label, 'value' => $value])
            ->values()
            ->all();
    }

    /**
     * Contact fields to prefill on the confirm card.
     *
     * @param  array<string, mixed>  $brief
     * @return array{name: string, email: string, phone: string, company: string, preferred_contact: string, preferred_locale: string}
     */
    public function contact(array $brief, string $locale): array
    {
        return [
            'name' => (string) ($brief['name'] ?? ''),
            'email' => (string) ($brief['email'] ?? ''),
            'phone' => (string) ($brief['phone'] ?? ''),
            'company' => (string) ($brief['company'] ?? ''),
            'preferred_contact' => (string) ($brief['preferred_contact'] ?? ContactMethod::Email->value),
            'preferred_locale' => (string) ($brief['preferred_locale'] ?? (Language::active()->has($locale) ? $locale : Language::defaultCode())),
        ];
    }
}
