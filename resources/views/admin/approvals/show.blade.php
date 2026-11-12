<x-layouts.admin :title="$changeRequest->summary" :crumb="__('Approvals')">
    @if ($errors->any())
        <x-ui.alert tone="danger" :title="__('Action blocked')">{{ $errors->first() }}</x-ui.alert>
    @endif

    <div class="grid gap-6 xl:grid-cols-[1fr_360px]">
        <x-ui.card>
            <div class="flex flex-wrap items-center gap-2">
                <span class="font-mono text-xs text-fg-tertiary">{{ $changeRequest->reference }}</span>
                <x-change-request-status :status="$changeRequest->status" />
                <x-ui.badge :dot="false">{{ strtoupper($changeRequest->origin->value) }}</x-ui.badge>
                <x-ui.badge :dot="false">{{ $changeRequest->action->value }}</x-ui.badge>
            </div>
            <p class="mt-3 text-sm text-fg-secondary">
                {{ __('Proposed by :actor · requires :permission · target version :version', ['actor' => $changeRequest->actor_label ?? __('System'), 'permission' => $changeRequest->required_permission->value, 'version' => $changeRequest->target_version]) }}
            </p>

            <div class="mt-6 grid gap-4 md:grid-cols-2">
                <div>
                    <p class="mb-2 text-sm font-semibold text-fg-danger">{{ __('Before (live)') }}</p>
                    <dl class="flex flex-col gap-3 rounded-lg bg-danger-subtle p-4">
                        @foreach (array_keys($changeRequest->proposed) as $field)
                            <div><dt class="text-xs font-semibold text-fg-tertiary">{{ $field }}</dt><dd class="text-sm text-fg" dir="auto">{{ $changeRequest->before[$field] ?? '—' }}</dd></div>
                        @endforeach
                    </dl>
                </div>
                <div>
                    <p class="mb-2 text-sm font-semibold text-fg-success">{{ __('Proposed') }}</p>
                    <dl class="flex flex-col gap-3 rounded-lg bg-success-subtle p-4">
                        @foreach ($changeRequest->proposed as $field => $value)
                            <div><dt class="text-xs font-semibold text-fg-tertiary">{{ $field }}</dt><dd class="text-sm text-fg" dir="auto">{{ $value }}</dd></div>
                        @endforeach
                    </dl>
                </div>
            </div>

            @if ($changeRequest->review_note)
                <x-ui.alert class="mt-6" :title="__('Review note by :name', ['name' => $changeRequest->reviewer?->name])">{{ $changeRequest->review_note }}</x-ui.alert>
            @endif
        </x-ui.card>

        <div class="flex flex-col gap-6">
            @if ($changeRequest->status->isReviewable())
                @can('review', $changeRequest)
                    <x-ui.card :title="__('Your decision')">
                        <form method="POST" action="{{ route('admin.approvals.decide', $changeRequest) }}" class="flex flex-col gap-4">
                            @csrf
                            <label for="note" class="text-sm font-semibold text-fg">{{ __('Review note') }}</label>
                            <textarea id="note" name="note" rows="4" class="rounded-md border border-line bg-surface px-3 py-2 text-sm text-fg">{{ old('note') }}</textarea>
                            <p class="text-xs text-fg-tertiary">{{ __('Required when requesting changes. Stored in the audit log.') }}</p>
                            <x-ui.button name="decision" value="approve">{{ __('Approve') }}</x-ui.button>
                            <x-ui.button name="decision" value="request_changes" variant="secondary">{{ __('Request changes') }}</x-ui.button>
                            <x-ui.button name="decision" value="reject" variant="ghost" class="text-fg-danger">{{ __('Reject') }}</x-ui.button>
                        </form>
                    </x-ui.card>
                @else
                    <x-ui.alert :title="__('You can’t review this request')">{{ __('It needs :permission, and you can’t approve your own proposals.', ['permission' => $changeRequest->required_permission->value]) }}</x-ui.alert>
                @endcan
            @elseif ($changeRequest->status === \App\Enums\ChangeRequestStatus::Approved)
                @can('apply', $changeRequest)
                    <x-ui.card :title="__('Apply to the live site')">
                        <p class="mb-4 text-sm text-fg-secondary">{{ __('Approval, version, permissions and emergency controls are verified again when you apply.') }}</p>
                        <form method="POST" action="{{ route('admin.approvals.apply', $changeRequest) }}" data-confirm="{{ __('Apply this change to the live site?') }}" data-confirm-label="{{ __('Apply') }}">
                            @csrf
                            <x-ui.button class="w-full">{{ __('Apply change') }}</x-ui.button>
                        </form>
                    </x-ui.card>
                @endcan
            @endif

            <x-ui.card :title="__('History')">
                <dl class="flex flex-col gap-2 text-sm">
                    <div class="flex justify-between gap-4"><dt class="text-fg-secondary">{{ __('Created') }}</dt><dd class="text-fg">{{ $changeRequest->created_at->format('Y-m-d H:i') }}</dd></div>
                    @if ($changeRequest->reviewed_at)
                        <div class="flex justify-between gap-4"><dt class="text-fg-secondary">{{ __('Reviewed') }}</dt><dd class="text-fg">{{ $changeRequest->reviewed_at->format('Y-m-d H:i') }} · {{ $changeRequest->reviewer?->name }}</dd></div>
                    @endif
                    @if ($changeRequest->applied_at)
                        <div class="flex justify-between gap-4"><dt class="text-fg-secondary">{{ __('Applied') }}</dt><dd class="text-fg">{{ $changeRequest->applied_at->format('Y-m-d H:i') }}</dd></div>
                    @endif
                    @if ($changeRequest->expires_at)
                        <div class="flex justify-between gap-4"><dt class="text-fg-secondary">{{ __('Expires') }}</dt><dd class="text-fg">{{ $changeRequest->expires_at->format('Y-m-d') }}</dd></div>
                    @endif
                </dl>
                @if (isset($changeRequest->apply_result['error']))
                    <x-ui.alert tone="danger" class="mt-4" :title="__('Apply failed')">{{ $changeRequest->apply_result['error'] }}</x-ui.alert>
                @endif
            </x-ui.card>
        </div>
    </div>
</x-layouts.admin>
