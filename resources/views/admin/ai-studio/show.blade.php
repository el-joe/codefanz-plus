@php
    $editable = $generation->status->isOpen() && ! $generation->is_dry_run && auth()->user()->can('use', $generation);
    $purpose = $generation->purpose;
    $languages = \App\Models\Language::active();
    $fieldLabel = fn (string $field): string => \App\Services\Ai\StudioTargets::fieldLabel($field);
    $rowTone = fn (string $type, string $side): string => match (true) {
        $type === 'same' => 'text-fg-secondary',
        $side === 'left' && in_array($type, ['removed', 'changed'], true) => 'bg-danger-subtle text-fg',
        $side === 'right' && in_array($type, ['added', 'changed'], true) => 'bg-success-subtle text-fg',
        default => '',
    };
@endphp

<x-layouts.admin :title="$generation->headline() ?? $purpose->label()" :crumb="__('AI Content Studio')">
    @if ($errors->any())
        <x-ui.alert tone="danger" :title="__('Action blocked')">{{ $errors->first() }}</x-ui.alert>
    @endif

    <div class="flex flex-wrap items-center gap-2" data-generation-meta>
        <x-ui.badge :tone="$generation->status->tone()">{{ $generation->status->label() }}</x-ui.badge>
        @if ($generation->is_dry_run)
            <x-ui.badge tone="info" :dot="false" data-dry-run>{{ __('Test run · cannot be used') }}</x-ui.badge>
        @endif
        <x-ui.badge :dot="false">{{ $purpose->label() }}</x-ui.badge>
        <span class="text-sm text-fg-secondary" dir="auto">
            {{ $generation->template ? __($generation->template->name) : __('Deleted template') }} · v{{ $generation->template_version }}
            · {{ $generation->user?->name }} · {{ $generation->created_at->format('Y-m-d H:i') }}
        </span>
        <span class="ms-auto font-mono text-xs text-fg-secondary" dir="ltr">{{ number_format($generation->input_tokens + $generation->output_tokens) }} tokens · ${{ number_format($generation->estimated_cost_minor / 100, 4) }} · {{ $generation->provider }} {{ $generation->model }}</span>
    </div>

    @if ($targetTitle)
        <p class="text-sm text-fg-secondary">{{ __('For :type: :title', ['type' => $targetLabel, 'title' => $targetTitle]) }}@if ($generation->mode === 'translate') · {{ __('English → Arabic translation') }}@endif</p>
    @endif

    @if ($generation->status === \App\Enums\AiGenerationStatus::Failed)
        <x-ui.alert tone="danger" :title="__('The provider call failed')">{{ $generation->error ?: __('Provider error') }}</x-ui.alert>
    @endif

    @if ($generation->hasFlags())
        <x-ui.alert tone="warning" :title="__('Verify before submitting')" data-guardrail-flags>
            <ul class="mt-1 flex flex-col gap-1">
                @foreach ($generation->guardrail_flags as $flag)
                    @php($type = \App\Enums\GuardrailFlagType::tryFrom($flag['type']))
                    <li data-flag="{{ $flag['type'] }}"><strong>{{ $type?->label() ?? $flag['type'] }}</strong> · {{ strtoupper($flag['locale']) }} · {{ $fieldLabel($flag['field']) }}: “<span dir="auto">{{ $flag['excerpt'] }}</span>” — {{ $type?->hint() }}</li>
                @endforeach
            </ul>
        </x-ui.alert>
    @endif

    @if ($generation->post)
        <x-ui.alert tone="success" :title="__('Blog draft created')">
            <a href="{{ route('admin.posts.edit', $generation->post) }}" class="font-semibold text-fg-brand hover:underline">{{ __('Open the draft in the blog editor') }}</a>
        </x-ui.alert>
    @endif

    @if ($generation->changeRequests->isNotEmpty())
        <x-ui.card :title="__('Change requests')">
            <ul class="flex flex-col gap-2 text-sm">
                @foreach ($generation->changeRequests as $changeRequest)
                    <li class="flex flex-wrap items-center gap-2">
                        <a href="{{ route('admin.approvals.show', $changeRequest) }}" class="font-mono font-semibold text-fg-brand hover:underline">{{ $changeRequest->reference }}</a>
                        <x-change-request-status :status="$changeRequest->status" />
                        <span class="text-fg-secondary" dir="auto">{{ $changeRequest->summary }}</span>
                    </li>
                @endforeach
            </ul>
        </x-ui.card>
    @endif

    @if ($output !== [])
        <form method="POST" action="{{ route('admin.ai-studio.generations.save', $generation) }}" class="flex flex-col gap-6" data-generation-form>
            @csrf
            @foreach ($output as $locale => $fields)
                @php($direction = $languages[$locale]['direction'] ?? 'ltr')
                <x-ui.card :title="__('Generated draft (:language)', ['language' => strtoupper($locale)])">
                    <div class="flex flex-col gap-6">
                        @foreach ($fields as $field => $value)
                            @php($live = $current[$locale][$field] ?? null)
                            <div class="flex flex-col gap-2" data-field="{{ $locale }}.{{ $field }}">
                                <div class="grid gap-4 {{ $live !== null ? 'lg:grid-cols-2' : '' }}">
                                    @if ($live !== null)
                                        <div class="flex flex-col gap-1.5">
                                            <p class="text-sm font-semibold text-fg-danger">{{ $fieldLabel($field) }} · {{ __('Live') }}</p>
                                            <div class="min-h-12 whitespace-pre-wrap break-words rounded-lg bg-danger-subtle px-3 py-2 text-sm text-fg" dir="{{ $direction }}" lang="{{ $locale }}">{{ $live !== '' ? $live : '—' }}</div>
                                        </div>
                                    @endif
                                    <div class="flex flex-col gap-1.5">
                                        <label for="out-{{ $locale }}-{{ $field }}" class="text-sm font-semibold {{ $live !== null ? 'text-fg-success' : 'text-fg' }}">{{ $fieldLabel($field) }} · {{ __('AI suggestion') }}</label>
                                        <textarea id="out-{{ $locale }}-{{ $field }}" name="outputs[{{ $locale }}][{{ $field }}]" dir="{{ $direction }}" lang="{{ $locale }}"
                                                  rows="{{ mb_strlen($value) > 400 ? 12 : (mb_strlen($value) > 120 ? 4 : 2) }}" @readonly(! $editable)
                                                  class="rounded-md border border-line-strong bg-surface px-3 py-2 text-sm text-fg read-only:bg-subtle">{{ old("outputs.{$locale}.{$field}", $value) }}</textarea>
                                    </div>
                                </div>
                                @if (isset($diffs[$locale][$field]) && collect($diffs[$locale][$field])->contains(fn ($row) => $row['type'] !== 'same'))
                                    <details class="rounded-lg border border-line-subtle">
                                        <summary class="cursor-pointer px-3 py-2 text-xs font-semibold text-fg-secondary">{{ __('Show text diff') }}</summary>
                                        <table class="w-full table-fixed text-sm" data-diff dir="{{ $direction }}" lang="{{ $locale }}">
                                            <caption class="sr-only">{{ __('Side-by-side comparison') }}</caption>
                                            <thead class="bg-subtle text-xs text-fg-tertiary"><tr><th scope="col" class="w-1/2 px-3 py-2 text-start">{{ __('Live') }}</th><th scope="col" class="w-1/2 px-3 py-2 text-start">{{ __('AI suggestion') }}</th></tr></thead>
                                            <tbody class="align-top">
                                                @foreach ($diffs[$locale][$field] as $row)
                                                    <tr class="border-t border-line-subtle" data-diff-row="{{ $row['type'] }}">
                                                        <td class="whitespace-pre-wrap break-words px-3 py-1.5 {{ $rowTone($row['type'], 'left') }}">@if ($row['left'] !== null && $row['type'] !== 'same')<span class="sr-only">{{ __('Removed:') }} </span>@endif{{ $row['left'] }}</td>
                                                        <td class="whitespace-pre-wrap break-words px-3 py-1.5 {{ $rowTone($row['type'], 'right') }}">@if ($row['right'] !== null && $row['type'] !== 'same')<span class="sr-only">{{ __('Added:') }} </span>@endif{{ $row['right'] }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </details>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </x-ui.card>
            @endforeach

            @if ($editable)
                <div class="flex flex-wrap items-center gap-3" data-generation-actions>
                    @if ($purpose === \App\Enums\PromptPurpose::BlogDraft)
                        @if ($canCreatePost)
                            <x-ui.button formaction="{{ route('admin.ai-studio.generations.draft', $generation) }}">{{ __('Create blog draft') }}</x-ui.button>
                        @else
                            <span class="text-sm text-fg-secondary">{{ __('Creating blog drafts needs the content.manage permission.') }}</span>
                        @endif
                    @elseif ($purpose->needsTarget())
                        <x-ui.button formaction="{{ route('admin.ai-studio.generations.propose', $generation) }}">{{ __('Send to approval') }}</x-ui.button>
                    @endif
                    <x-ui.button variant="secondary">{{ __('Save private draft') }}</x-ui.button>
                    <x-ui.button variant="ghost" class="ms-auto text-fg-danger" formaction="{{ route('admin.ai-studio.generations.discard', $generation) }}" formnovalidate>{{ __('Discard') }}</x-ui.button>
                </div>
                <p class="text-xs text-fg-tertiary">{{ __('AI output may contain errors. A human reviewer must approve it before anything is published.') }}</p>
            @endif
        </form>
    @endif
</x-layouts.admin>
