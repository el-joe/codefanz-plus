@if ($errors->has('milestone'))
    <x-ui.alert tone="danger" :title="__('Milestone not updated')">{{ $errors->first('milestone') }}</x-ui.alert>
@endif

@if ($project->milestones->isEmpty())
    <x-ui.card padding="p-6">
        @include('portal._empty-module', ['title' => __('No milestones yet'), 'description' => __('Your project manager will add the milestones of this project soon.'), 'icon' => '<path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><path d="M4 22v-7"/>'])
    </x-ui.card>
@else
    <ol class="flex flex-col gap-4" data-milestones>
        @foreach ($project->milestones as $milestone)
            @php($awaiting = $milestone->status === \App\Enums\MilestoneStatus::AwaitingApproval)
            <li class="rounded-xl border bg-surface p-5 {{ $awaiting ? 'border-warning' : 'border-line' }}" data-milestone="{{ $milestone->ulid }}">
                <div class="flex flex-wrap items-start gap-3">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-lg text-sm font-semibold {{ $milestone->status->isDone() ? 'bg-success-subtle text-fg-success' : ($awaiting ? 'bg-warning-subtle text-fg-warning' : 'bg-muted text-fg-secondary') }}">M{{ $loop->iteration }}</span>
                    <div class="min-w-0 flex-1">
                        <h3 class="text-base font-semibold text-fg" dir="auto">{{ $milestone->title }}</h3>
                        <p class="text-sm text-fg-secondary">
                            {{ $milestone->due_date ? __('Due :date', ['date' => $milestone->due_date->translatedFormat('j M Y')]) : __('Due date to be confirmed') }}
                            @if ($project->show_budget_to_client && $milestone->amount_minor > 0) · <span dir="ltr">{{ $milestone->formattedAmount() }}</span>@endif
                        </p>
                    </div>
                    <x-ui.badge :tone="$milestone->status->tone()">{{ $awaiting ? __('Awaiting your approval') : $milestone->status->label() }}</x-ui.badge>
                </div>
                @if (filled($milestone->description))
                    <p class="mt-3 whitespace-pre-line text-sm text-fg" dir="auto">{{ $milestone->description }}</p>
                @endif
                @if ($milestone->dependencies->isNotEmpty())
                    <p class="mt-2 text-xs text-fg-tertiary" dir="auto">{{ __('Starts after: :titles', ['titles' => $milestone->dependencies->pluck('title')->implode(', ')]) }}</p>
                @endif
                @if ($milestone->approved_at)
                    <p class="mt-2 text-xs text-fg-success">{{ __('Approved on :date', ['date' => $milestone->approved_at->translatedFormat('j M Y')]) }}</p>
                @endif

                @if ($awaiting)
                    <div class="mt-4 flex flex-col gap-3 border-t border-line-subtle pt-4">
                        <form method="POST" action="{{ route('portal.projects.milestones.review', [$project, $milestone]) }}">
                            @csrf
                            <input type="hidden" name="decision" value="approve">
                            <x-ui.button size="sm">{{ __('Approve milestone') }}</x-ui.button>
                        </form>
                        <details>
                            <summary class="cursor-pointer text-sm font-semibold text-fg-brand">{{ __('Request changes') }}</summary>
                            <form method="POST" action="{{ route('portal.projects.milestones.review', [$project, $milestone]) }}" class="mt-3 flex flex-col gap-3">
                                @csrf
                                <input type="hidden" name="decision" value="changes">
                                <label for="feedback-{{ $milestone->ulid }}" class="text-sm font-semibold text-fg">{{ __('What should change?') }}</label>
                                <textarea id="feedback-{{ $milestone->ulid }}" name="feedback" rows="3" maxlength="2000" required dir="auto"
                                          class="w-full rounded-md border border-line bg-surface px-3 py-2 text-sm text-fg focus:border-line-focus focus:outline-none focus:ring-4 focus:ring-brand/20">{{ old('feedback') }}</textarea>
                                @error('feedback')<p class="text-sm text-fg-danger">{{ $message }}</p>@enderror
                                <x-ui.button size="sm" variant="secondary" class="w-fit">{{ __('Send change request') }}</x-ui.button>
                            </form>
                        </details>
                    </div>
                @endif
            </li>
        @endforeach
    </ol>
@endif
