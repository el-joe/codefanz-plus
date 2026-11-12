{{-- Seller block for invoices and receipts. Expects $seller. --}}
<div>
    <x-ui.logo />
    <p class="mt-3 font-semibold" dir="auto">{{ $seller['name'] }}</p>
    @if ($seller['address'])<p class="text-sm text-ink-600" dir="auto">{{ $seller['address'] }}</p>@endif
    @if ($seller['email'])<p class="text-sm text-ink-600" dir="ltr">{{ $seller['email'] }}</p>@endif
    @if ($seller['phone'])<p class="text-sm text-ink-600" dir="ltr">{{ $seller['phone'] }}</p>@endif
</div>
