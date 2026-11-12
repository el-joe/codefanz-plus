<x-layouts.admin :title="__('Message from :name', ['name' => $message->name])" :crumb="__('Contact inbox')">
    <div class="grid gap-6 xl:grid-cols-[1fr_340px]">
        <x-ui.card :title="$message->topic->label()">
            <p class="whitespace-pre-line text-base leading-relaxed text-fg" dir="auto">{{ $message->message }}</p>
        </x-ui.card>

        <x-ui.card :title="__('Details')">
            <dl class="flex flex-col divide-y divide-line-subtle text-sm">
                @foreach (array_filter([
                    __('Name') => $message->name,
                    __('Email') => $message->email,
                    __('Phone') => $message->phone,
                    __('Reply in') => \App\Models\Language::active()[$message->preferred_locale]['native_name'] ?? strtoupper($message->preferred_locale),
                    __('Sent from') => strtoupper($message->locale).' · '.__('website'),
                    __('Received') => $message->created_at->translatedFormat('j M Y, H:i'),
                    __('Consent given') => $message->consented_at->translatedFormat('j M Y, H:i'),
                ]) as $label => $value)
                    <div class="flex justify-between gap-4 py-2.5">
                        <dt class="text-fg-tertiary">{{ $label }}</dt>
                        <dd class="text-end font-semibold text-fg" dir="auto">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
            <div class="mt-4 flex flex-col gap-2">
                <x-ui.button :href="'mailto:'.$message->email.'?subject='.rawurlencode('Re: '.$message->topic->label())">{{ __('Reply by email') }}</x-ui.button>
                @if ($message->lead)
                    <a href="{{ route('admin.leads.show', $message->lead) }}" class="text-sm font-semibold text-fg-brand hover:underline">{{ __('Open lead :reference', ['reference' => $message->lead->reference]) }}</a>
                @endif
                <a href="{{ route('admin.contact-messages.index') }}" class="text-sm font-semibold text-fg-brand hover:underline">{{ __('Back to inbox') }}</a>
            </div>
        </x-ui.card>
    </div>
</x-layouts.admin>
