@php
    $lines = fn (?array $items): string => implode("\n", $items ?? []);
@endphp

<x-layouts.admin :title="__('Brand voice')" :crumb="__('AI Content Studio')">
    <p class="max-w-3xl text-sm text-fg-secondary">{{ __('Every Content Studio prompt includes this profile. Banned claims and approved domains are also checked again after each generation.') }}</p>

    @if ($errors->any())
        <x-ui.alert tone="danger" :title="__('Please fix the highlighted fields')">{{ $errors->first() }}</x-ui.alert>
    @endif

    <form method="POST" action="{{ route('admin.brand-voice.update') }}" class="flex max-w-4xl flex-col gap-6">
        @csrf
        @method('PUT')
        <input type="hidden" name="lock_version" value="{{ $profile->lock_version }}">

        <x-ui.card>
            <x-ui.input name="name" :label="__('Brand name')" :value="$profile->name" required maxlength="120" />
        </x-ui.card>

        <div class="grid gap-6 lg:grid-cols-2">
            @foreach (['en' => 'English', 'ar' => 'العربية'] as $code => $languageName)
                <x-ui.card :title="$languageName" :lang="$code" :dir="$code === 'ar' ? 'rtl' : 'ltr'">
                    <div class="flex flex-col gap-4">
                        @include('admin._textarea', ['name' => "tone[{$code}]", 'id' => "tone-{$code}", 'label' => __('Tone of voice'), 'value' => $profile->tone[$code] ?? '', 'rows' => 3, 'max' => 1000])
                        @include('admin._textarea', ['name' => "audience[{$code}]", 'id' => "audience-{$code}", 'label' => __('Audience'), 'value' => $profile->audience[$code] ?? '', 'rows' => 2, 'max' => 1000])
                        @include('admin._textarea', ['name' => "do_rules_text[{$code}]", 'id' => "do-{$code}", 'label' => __('Always (one rule per line)'), 'value' => $lines($profile->do_rules[$code] ?? []), 'rows' => 4])
                        @include('admin._textarea', ['name' => "dont_rules_text[{$code}]", 'id' => "dont-{$code}", 'label' => __('Never (one rule per line)'), 'value' => $lines($profile->dont_rules[$code] ?? []), 'rows' => 4])
                    </div>
                </x-ui.card>
            @endforeach
        </div>

        <x-ui.card :title="__('Checks and terminology')">
            <div class="flex flex-col gap-4">
                @include('admin._textarea', ['name' => 'banned_claims_text', 'id' => 'banned-claims', 'label' => __('Banned claims (one per line, any language)'), 'value' => $lines($profile->banned_claims), 'rows' => 4, 'help' => __('For example guarantees, “#1”, invented proof. Matching output is flagged for reviewers.')])
                @include('admin._textarea', ['name' => 'glossary_text', 'id' => 'glossary', 'label' => __('Glossary (English term = Arabic term, one per line)'), 'value' => implode("\n", array_map(fn ($pair) => $pair['en'].' = '.$pair['ar'], $profile->glossaryPairs())), 'rows' => 6, 'help' => __('Used when writing and when translating English into Arabic.')])
                @include('admin._textarea', ['name' => 'allowed_domains_text', 'id' => 'allowed-domains', 'label' => __('Other approved link domains (one per line)'), 'value' => $lines($profile->allowed_domains), 'rows' => 3, 'mono' => true, 'help' => __('Links to this website are always allowed. Links elsewhere are flagged.')])
            </div>
        </x-ui.card>

        <div><x-ui.button>{{ __('Save brand voice') }}</x-ui.button></div>
    </form>
</x-layouts.admin>
