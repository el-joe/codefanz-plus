<x-layouts.portal :title="__('New ticket')" :crumb="__('Support')">
    <x-ui.card class="max-w-3xl">
        <form method="POST" action="{{ route('portal.support.store') }}" class="flex flex-col gap-4" data-ticket-form>
            @csrf
            <x-ui.input name="subject" :label="__('Subject')" required maxlength="160" dir="auto" />
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <x-ui.select name="category" :label="__('Category')" :options="\App\Enums\TicketCategory::options()" value="question" />
                <x-ui.select name="priority" :label="__('Priority')" :options="\App\Enums\TicketPriority::options()" value="normal" />
                <x-ui.select name="project" :label="__('Project (optional)')" :options="$projects" :placeholder="__('Not about a project')" />
            </div>
            <div class="flex flex-col gap-1.5">
                <label for="ticket-body" class="text-sm font-semibold text-fg">{{ __('Describe the issue') }}</label>
                <textarea id="ticket-body" name="body" rows="6" required maxlength="{{ config('codefanz.support.reply_max_length') }}" dir="auto"
                          class="rounded-md border bg-surface px-3 py-2 text-sm text-fg {{ $errors->has('body') ? 'border-line-danger' : 'border-line-strong' }}">{{ old('body') }}</textarea>
                @error('body')<p class="text-sm text-fg-danger">{{ $message }}</p>@enderror
                <p class="text-xs text-fg-tertiary">{{ __('What happened, what you expected, and steps to reproduce if you can.') }}</p>
            </div>
            <div class="flex items-center gap-3">
                <x-ui.button>{{ __('Open ticket') }}</x-ui.button>
                <a href="{{ route('portal.support.index') }}" class="text-sm font-semibold text-fg-brand hover:underline">{{ __('Cancel') }}</a>
            </div>
        </form>
    </x-ui.card>
</x-layouts.portal>
