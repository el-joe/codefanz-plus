<x-layouts.admin :title="__('Contact inbox')" :crumb="__('Sales')">
    <div class="flex flex-wrap items-end gap-3">
        <p class="flex-1 text-sm text-fg-secondary">{{ __('Messages sent through the website contact form. They become leads when the CRM arrives; for now, reply by email.') }}</p>
        <form method="GET" class="flex items-end gap-2">
            <x-ui.select name="topic" :label="__('Subject')" :options="\App\Enums\ContactTopic::options()" :value="$topic" :placeholder="__('All subjects')" />
            <x-ui.button variant="secondary">{{ __('Filter') }}</x-ui.button>
        </form>
    </div>

    <x-ui.card padding="p-0" class="overflow-x-auto">
        @if ($messages->isEmpty())
            <x-ui.empty-state :title="__('No messages yet')" :description="__('Messages from the contact page will appear here.')" />
        @else
            <table class="w-full text-sm">
                <thead class="bg-subtle text-xs text-fg-tertiary">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('From') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Subject') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Message') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Reply in') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Received') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($messages as $message)
                        <tr class="border-t border-line-subtle">
                            <td class="px-4 py-3">
                                <a href="{{ route('admin.contact-messages.show', $message) }}" class="font-semibold text-fg hover:text-fg-brand" dir="auto">{{ $message->name }}</a>
                                <p class="text-xs text-fg-tertiary" dir="ltr">{{ $message->email }}</p>
                            </td>
                            <td class="px-4 py-3"><x-ui.badge :dot="false">{{ $message->topic->label() }}</x-ui.badge></td>
                            <td class="max-w-md px-4 py-3 text-fg-secondary" dir="auto">{{ \Illuminate\Support\Str::limit($message->message, 90) }}</td>
                            <td class="px-4 py-3 text-fg-secondary">{{ strtoupper($message->preferred_locale) }}</td>
                            <td class="px-4 py-3 text-fg-secondary"><time datetime="{{ $message->created_at->toIso8601String() }}">{{ $message->created_at->translatedFormat('j M Y, H:i') }}</time></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-ui.card>

    {{ $messages->links() }}
</x-layouts.admin>
