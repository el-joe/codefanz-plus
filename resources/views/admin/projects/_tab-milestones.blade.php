@if ($errors->has('status'))
    <x-ui.alert tone="danger" :title="__('Milestone not updated')">{{ $errors->first('status') }}</x-ui.alert>
@endif

<x-ui.card padding="p-0" class="overflow-x-auto">
    <div class="flex items-center gap-3 px-5 py-4">
        <h2 class="flex-1 text-lg font-semibold text-fg">{{ __('Milestones') }}</h2>
        @if ($canManage)
            <x-ui.button size="sm" :href="route('admin.projects.milestones.create', $project)">{{ __('Add milestone') }}</x-ui.button>
        @endif
    </div>
    @if ($project->milestones->isEmpty())
        <x-ui.empty-state :title="__('No milestones yet')" :description="__('Break the project into milestones the client can follow and approve.')" />
    @else
        <table class="w-full text-sm" data-milestones>
            <thead class="bg-subtle text-xs text-fg-tertiary">
                <tr>
                    <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Milestone') }}</th>
                    <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Due date') }}</th>
                    <th scope="col" class="px-4 py-3 text-end font-semibold">{{ __('Amount') }}</th>
                    <th scope="col" class="px-4 py-3 text-end font-semibold">{{ __('Paid') }}</th>
                    <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Status') }}</th>
                    <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Payment') }}</th>
                    <th scope="col" class="px-4 py-3 text-end font-semibold"><span class="sr-only">{{ __('Actions') }}</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($project->milestones as $milestone)
                    @php($blocking = $milestone->blockingDependencies())
                    <tr class="border-t border-line-subtle align-top" data-milestone="{{ $milestone->ulid }}">
                        <td class="px-4 py-3">
                            <p class="font-semibold text-fg" dir="auto"><span class="me-1 font-mono text-xs text-fg-tertiary">M{{ $loop->iteration }}</span>{{ $milestone->title }}</p>
                            @if ($milestone->dependencies->isNotEmpty())
                                <p class="mt-1 text-xs text-fg-tertiary" dir="auto">{{ __('Starts after: :titles', ['titles' => $milestone->dependencies->pluck('title')->implode(', ')]) }}</p>
                            @endif
                            @if (filled($milestone->client_feedback))
                                <p class="mt-1 text-xs text-fg-warning" dir="auto">{{ __('Client asked for changes: :feedback', ['feedback' => $milestone->client_feedback]) }}</p>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-fg-secondary">{{ $milestone->due_date?->translatedFormat('j M Y') ?? '—' }}</td>
                        <td class="px-4 py-3 text-end text-fg" dir="ltr">{{ $milestone->formattedAmount() }}</td>
                        <td class="px-4 py-3 text-end text-fg-secondary" dir="ltr">{{ \App\Services\Money::format($milestone->paid_minor, $milestone->currency) }}</td>
                        <td class="px-4 py-3"><x-ui.badge :tone="$milestone->status->tone()">{{ $milestone->status->label() }}</x-ui.badge></td>
                        <td class="px-4 py-3" data-payment-state="{{ $milestone->paymentState() }}">
                            @switch($milestone->paymentState())
                                @case('paid')<x-ui.badge tone="success">{{ __('Paid') }}</x-ui.badge>@break
                                @case('partial')<x-ui.badge tone="warning">{{ __('Partial') }}</x-ui.badge>@break
                                @default<x-ui.badge>{{ __('Not billed') }}</x-ui.badge>
                            @endswitch
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap justify-end gap-1.5">
                                @if (isset($linkDialog) && ($linkDialog['remaining'][$milestone->ulid] ?? 0) > 0)
                                    <x-ui.button size="sm" variant="secondary" type="button" data-dialog-open="payment-link-dialog" data-milestone="{{ $milestone->ulid }}">{{ __('Payment link') }}</x-ui.button>
                                @endif
                                @can('create', \App\Models\Invoice::class)
                                    @if ($milestone->amount_minor > 0)
                                        <form method="POST" action="{{ route('admin.projects.milestones.invoice', [$project, $milestone]) }}">
                                            @csrf
                                            <x-ui.button size="sm" variant="ghost">{{ __('Invoice') }}</x-ui.button>
                                        </form>
                                    @endif
                                @endcan
                            </div>
                            @if ($canManage)
                                <div class="mt-1.5 flex flex-wrap justify-end gap-1.5">
                                    @foreach ($milestone->status->staffTransitions() as $next)
                                        @php($disabled = $next === \App\Enums\MilestoneStatus::InProgress && $milestone->status === \App\Enums\MilestoneStatus::Pending && $blocking->isNotEmpty())
                                        <form method="POST" action="{{ route('admin.projects.milestones.status', [$project, $milestone]) }}">
                                            @csrf
                                            <input type="hidden" name="status" value="{{ $next->value }}">
                                            <x-ui.button size="sm" :variant="$next === \App\Enums\MilestoneStatus::AwaitingApproval ? 'primary' : 'secondary'" :disabled="$disabled" :title="$disabled ? __('Waiting for: :titles', ['titles' => $blocking->pluck('title')->implode(', ')]) : null">{{ $next->actionLabel() }}</x-ui.button>
                                        </form>
                                    @endforeach
                                    <x-ui.button size="sm" variant="ghost" :href="route('admin.projects.milestones.edit', [$project, $milestone])">{{ __('Edit') }}</x-ui.button>
                                    @foreach (['up' => ['↑', __('Move up')], 'down' => ['↓', __('Move down')]] as $direction => [$arrow, $label])
                                        @continue(($direction === 'up' && $loop->parent->first) || ($direction === 'down' && $loop->parent->last))
                                        <form method="POST" action="{{ route('admin.projects.milestones.move', [$project, $milestone]) }}">
                                            @csrf
                                            <input type="hidden" name="direction" value="{{ $direction }}">
                                            <button type="submit" class="rounded-md px-2 py-1 text-sm text-fg-secondary hover:bg-subtle" aria-label="{{ $label }}">{{ $arrow }}</button>
                                        </form>
                                    @endforeach
                                    <form method="POST" action="{{ route('admin.projects.milestones.destroy', [$project, $milestone]) }}" data-confirm="{{ __('Delete milestone “:title”?', ['title' => $milestone->title]) }}" data-confirm-label="{{ __('Delete') }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="rounded-md px-2 py-1 text-sm text-fg-danger hover:bg-subtle">{{ __('Delete') }}</button>
                                    </form>
                                </div>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot class="bg-subtle text-sm">
                <tr>
                    <td class="px-4 py-3 font-semibold text-fg" colspan="2">{{ __('Total') }}</td>
                    <td class="px-4 py-3 text-end font-semibold text-fg" dir="ltr">{{ $milestonesTotal }}</td>
                    <td class="px-4 py-3 text-end font-semibold text-fg-success" dir="ltr">{{ \App\Services\Money::format((int) $project->milestones->sum('paid_minor'), $project->currency) }}</td>
                    <td class="px-4 py-3 text-fg-secondary" colspan="3">{{ __('Collected amounts come from verified payments only.') }}</td>
                </tr>
            </tfoot>
        </table>
    @endif
</x-ui.card>
