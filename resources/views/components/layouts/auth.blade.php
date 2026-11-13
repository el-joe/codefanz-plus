<x-layouts.base :title="$title ?? null">
    <div class="grid grid-cols-1 min-h-screen lg:grid-cols-[minmax(0,560px)_1fr]">
        <aside class="dark hidden flex-col justify-between bg-[linear-gradient(135deg,#3B26AB,#0A0D17)] p-14 text-fg lg:flex">
            <x-ui.logo />
            <div class="flex flex-col gap-5">
                <h2 class="text-3xl font-bold leading-tight">{{ __('Track your projects, milestones and payments in one place.') }}</h2>
                <ul class="flex flex-col gap-3 text-fg-secondary">
                    <li>{{ __('Live progress for every milestone') }}</li>
                    <li>{{ __('Invoices and secure payment links') }}</li>
                    <li>{{ __('Documents and conversations with your team') }}</li>
                </ul>
            </div>
            <p class="text-xs text-fg-tertiary">© {{ now()->year }} CodeFanz</p>
        </aside>
        <main id="main" class="flex flex-col items-center justify-center px-5 py-10">
            <div class="flex w-full max-w-md flex-col gap-6">
                <div class="flex items-center justify-between">
                    <x-ui.logo class="lg:invisible" />
                    <x-language-switch />
                </div>
                {{ $slot }}
            </div>
        </main>
    </div>
</x-layouts.base>
