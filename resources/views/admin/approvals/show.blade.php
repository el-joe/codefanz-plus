@php
    $rowTone = fn (string $type, string $side): string => match (true) {
        $type === 'same' => 'text-fg-secondary',
        $side === 'left' && in_array($type, ['removed', 'changed'], true) => 'bg-danger-subtle text-fg',
        $side === 'right' && in_array($type, ['added', 'changed'], true) => 'bg-success-subtle text-fg',
        default => '',
    };
    $direction = $changeRequest->locale ? (\App\Models\Language::active()[$changeRequest->locale]['direction'] ?? 'ltr') : 'ltr';
    $fieldLabel = fn (string $field): string => \App\Services\Ai\StudioTargets::fieldLabel($field);
@endphp

<x-layouts.admin :title="__('Approval queue')" :crumb="__('AI & Automation')">
    @if ($errors->any())
        <x-ui.alert tone="danger" :title="__('Action blocked')" data-action-blocked>{{ $errors->first() }}</x-ui.alert>
    @endif

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[340px_minmax(0,1fr)]">
        <x-ui.card padding="p-0" class="hidden self-start overflow-hidden xl:block">
            <h2 class="border-b border-line px-4 py-3 text-sm font-semibold text-fg">{{ __('Pending') }}</h2>
            @forelse ($queue as $item)
                <a href="{{ route('admin.approvals.show', $item) }}" @if ($item->is($changeRequest)) aria-current="page" @endif
                   class="block border-b border-line-subtle px-4 py-3 last:border-0 {{ $item->is($changeRequest) ? 'bg-brand-subtle' : 'hover:bg-subtle' }}">
                    <span class="flex items-center gap-2"><span class="font-mono text-xs text-fg-tertiary">{{ $item->reference }}</span><x-ui.badge :dot="false">{{ strtoupper($item->origin->value) }}</x-ui.badge></span>
                    <span class="mt-1 block text-sm font-semibold text-fg" dir="auto">{{ $item->summary }}</span>
                    <span class="block text-xs text-fg-tertiary">{{ $item->actor_label }} · {{ $item->created_at->diffForHumans() }}</span>
                </a>
            @empty
                <p class="px-4 py-3 text-sm text-fg-secondary">{{ __('Nothing else is waiting.') }}</p>
            @endforelse
        </x-ui.card>

        <x-ui.card>
            <div class="flex flex-wrap items-center gap-2">
                <span class="font-mono text-xs text-fg-tertiary">{{ $changeRequest->reference }}</span>
                <x-change-request-status :status="$changeRequest->status" />
                <x-ui.badge :tone="$changeRequest->origin === \App\Enums\ChangeOrigin::Mcp ? 'info' : 'accent'" :dot="false">{{ strtoupper($changeRequest->origin->value) }}</x-ui.badge>
                <x-ui.badge :dot="false">{{ $changeRequest->action->value }}</x-ui.badge>
                @if ($changeRequest->locale)<x-ui.badge :dot="false">{{ strtoupper($changeRequest->locale) }}</x-ui.badge>@endif
            </div>
            <h2 class="mt-3 text-2xl font-semibold text-fg" dir="auto">{{ $changeRequest->summary }}</h2>
            <p class="mt-1 text-sm text-fg-secondary" dir="auto">
                {{ __('Proposed by :actor · requires :permission · target version :version', ['actor' => $changeRequest->actor_label ?? __('System'), 'permission' => $changeRequest->required_permission->value, 'version' => $changeRequest->target_version]) }}
                @if ($changeRequest->generation)
                    · <a href="{{ route('admin.ai-studio.generations.show', $changeRequest->generation) }}" class="font-semibold text-fg-brand hover:underline" data-generation-link>{{ __('View generation record') }}</a>
                @endif
            </p>

            @if ($changeRequest->hasGuardrailFlags())
                <x-ui.alert tone="warning" class="mt-5" :title="__('Automatic checks flagged this proposal')" data-guardrail-flags>
                    <ul class="mt-1 flex flex-col gap-1">
                        @foreach ($changeRequest->guardrail_flags as $flag)
                            @php($type = \App\Enums\GuardrailFlagType::tryFrom($flag['type'] ?? ''))
                            <li data-flag="{{ $flag['type'] ?? '' }}"><strong>{{ $type?->label() ?? ($flag['type'] ?? '') }}</strong> · {{ $fieldLabel($flag['field'] ?? '') }}: “<span dir="auto">{{ $flag['excerpt'] ?? '' }}</span>”</li>
                        @endforeach
                    </ul>
                </x-ui.alert>
            @endif

            <div class="mt-6 flex flex-col gap-6">
                @foreach ($fields as $field => $change)
                    <section data-diff-field="{{ $field }}">
                        <h3 class="mb-2 text-sm font-semibold text-fg">{{ $fieldLabel($field) }}</h3>
                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                            <div>
                                <p class="mb-1 text-xs font-semibold text-fg-danger">{{ __('Before (live · v:version)', ['version' => $changeRequest->target_version]) }}</p>
                                <div class="whitespace-pre-wrap break-words rounded-lg bg-danger-subtle p-3 text-sm text-fg" dir="{{ $direction }}">{{ $change['before'] !== '' ? strip_tags($change['before']) : '—' }}</div>
                            </div>
                            <div>
                                <p class="mb-1 text-xs font-semibold text-fg-success">{{ __('Proposed') }}</p>
                                <div class="whitespace-pre-wrap break-words rounded-lg bg-success-subtle p-3 text-sm text-fg" dir="{{ $direction }}">{{ strip_tags($change['after']) }}</div>
                            </div>
                        </div>
                        @if (count($change['rows']) > 1 || collect($change['rows'])->contains(fn ($row) => $row['type'] !== 'changed'))
                            <details class="mt-2 rounded-lg border border-line-subtle">
                                <summary class="cursor-pointer px-3 py-2 text-xs font-semibold text-fg-secondary">{{ __('Show line-by-line diff') }}</summary>
                                <table class="w-full table-fixed text-sm" data-diff dir="{{ $direction }}">
                                    <caption class="sr-only">{{ __('Side-by-side comparison') }}</caption>
                                    <tbody class="align-top">
                                        @foreach ($change['rows'] as $row)
                                            <tr class="border-t border-line-subtle" data-diff-row="{{ $row['type'] }}">
                                                <td class="whitespace-pre-wrap break-words px-3 py-1.5 {{ $rowTone($row['type'], 'left') }}">@if ($row['left'] !== null && $row['type'] !== 'same')<span class="sr-only">{{ __('Removed:') }} </span>@endif{{ $row['left'] }}</td>
                                                <td class="whitespace-pre-wrap break-words px-3 py-1.5 {{ $rowTone($row['type'], 'right') }}">@if ($row['right'] !== null && $row['type'] !== 'same')<span class="sr-only">{{ __('Added:') }} </span>@endif{{ $row['right'] }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </details>
                        @endif
                    </section>
                @endforeach
            </div>

            <div class="mt-6 rounded-lg bg-subtle p-4" data-verified-checks>
                <p class="mb-2 text-xs font-semibold text-fg-tertiary">{{ __('Verified again when you apply') }}</p>
                <ul class="flex flex-col gap-1.5 text-sm">
                    @foreach ($checks as $check)
                        <li class="flex items-start gap-2 {{ $check['ok'] ? 'text-fg' : 'text-fg-danger' }}" data-check="{{ $check['ok'] ? 'ok' : 'fail' }}">
                            <span aria-hidden="true" class="font-bold {{ $check['ok'] ? 'text-fg-success' : 'text-fg-danger' }}">{{ $check['ok'] ? '✓' : '✕' }}</span>
                            <span><span class="sr-only">{{ $check['ok'] ? __('Passed:') : __('Failed:') }} </span>{{ $check['label'] }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>

            @if ($changeRequest->review_note)
                <x-ui.alert class="mt-6" :title="__('Review note by :name', ['name' => $changeRequest->reviewer?->name])">{{ $changeRequest->review_note }}</x-ui.alert>
            @endif

            @if (isset($changeRequest->apply_result['error']))
                <x-ui.alert tone="danger" class="mt-6" :title="__('Apply failed')">{{ $changeRequest->apply_result['error'] }}</x-ui.alert>
            @endif

            @if ($changeRequest->status->isReviewable())
                @can('review', $changeRequest)
                    <form method="POST" action="{{ route('admin.approvals.decide', $changeRequest) }}" class="mt-6 flex flex-col gap-2" data-decision-form>
                        @csrf
                        <label for="note" class="text-sm font-semibold text-fg">{{ __('Review note') }}</label>
                        <textarea id="note" name="note" rows="4" maxlength="1000" placeholder="{{ __('Optional for approval, required when requesting changes…') }}" class="rounded-md border border-line-strong bg-surface px-3 py-2 text-sm text-fg">{{ old('note') }}</textarea>
                        <p class="text-xs text-fg-tertiary">{{ __('Stored in the audit log · up to 1,000 characters') }}</p>
                        <div class="mt-2 flex flex-wrap items-center gap-3">
                            <x-ui.button name="decision" value="reject" variant="ghost" class="text-fg-danger">{{ __('Reject') }}</x-ui.button>
                            <x-ui.button name="decision" value="request_changes" variant="secondary" class="ms-auto">{{ __('Request changes') }}</x-ui.button>
                            <x-ui.button name="decision" value="approve" variant="secondary">{{ __('Approve') }}</x-ui.button>
                            <x-ui.button name="decision" value="approve_apply">{{ __('Approve & apply') }}</x-ui.button>
                        </div>
                    </form>
                @else
                    <x-ui.alert class="mt-6" :title="__('You can’t review this request')">{{ __('It needs :permission, and you can’t approve your own proposals.', ['permission' => $changeRequest->required_permission->value]) }}</x-ui.alert>
                @endcan
            @elseif ($changeRequest->status === \App\Enums\ChangeRequestStatus::Approved)
                @can('apply', $changeRequest)
                    <form method="POST" action="{{ route('admin.approvals.apply', $changeRequest) }}" class="mt-6" data-confirm="{{ __('Apply this change to the live site?') }}" data-confirm-label="{{ __('Apply') }}">
                        @csrf
                        <x-ui.button>{{ __('Apply change') }}</x-ui.button>
                    </form>
                @endcan
            @endif

            <dl class="mt-6 flex flex-col gap-2 border-t border-line pt-4 text-sm">
                <div class="flex justify-between gap-4"><dt class="text-fg-secondary">{{ __('Created') }}</dt><dd class="text-fg">{{ $changeRequest->created_at->format('Y-m-d H:i') }}</dd></div>
                @if ($changeRequest->reviewed_at)
                    <div class="flex justify-between gap-4"><dt class="text-fg-secondary">{{ __('Reviewed') }}</dt><dd class="text-fg">{{ $changeRequest->reviewed_at->format('Y-m-d H:i') }} · {{ $changeRequest->reviewer?->name }}</dd></div>
                @endif
                @if ($changeRequest->applied_at)
                    <div class="flex justify-between gap-4"><dt class="text-fg-secondary">{{ __('Applied') }}</dt><dd class="text-fg">{{ $changeRequest->applied_at->format('Y-m-d H:i') }}</dd></div>
                @endif
                @if ($changeRequest->expires_at)
                    <div class="flex justify-between gap-4"><dt class="text-fg-secondary">{{ __('Expires') }}</dt><dd class="{{ $changeRequest->isExpired() ? 'text-fg-danger' : 'text-fg' }}" data-expiry>{{ $changeRequest->expires_at->format('Y-m-d') }}@if ($changeRequest->isExpired()) · {{ __('Expired') }}@endif</dd></div>
                @endif
            </dl>
        </x-ui.card>
    </div>
</x-layouts.admin>
