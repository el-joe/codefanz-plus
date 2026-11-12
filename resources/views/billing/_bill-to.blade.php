{{-- Bill-to block. Expects $billTo (snapshot array) and $heading. --}}
<div>
    <p class="text-xs font-semibold uppercase tracking-wide text-ink-500">{{ $heading }}</p>
    <p class="mt-1 font-semibold" dir="auto" data-bill-to-name>{{ $billTo['name'] }}</p>
    @if (! empty($billTo['contact']))<p class="text-sm text-ink-600" dir="auto">{{ $billTo['contact'] }}</p>@endif
    @foreach ($billTo['address'] ?? [] as $line)
        <p class="text-sm text-ink-600" dir="auto">{{ $line }}</p>
    @endforeach
    @if (! empty($billTo['country']))<p class="text-sm text-ink-600">{{ app(\App\Services\Countries::class)->name($billTo['country']) }}</p>@endif
    @if (! empty($billTo['email']))<p class="text-sm text-ink-600" dir="ltr">{{ $billTo['email'] }}</p>@endif
    @if (! empty($billTo['tax_id']))<p class="text-sm text-ink-600">{{ __('Tax ID') }}: <span dir="ltr">{{ $billTo['tax_id'] }}</span></p>@endif
</div>
