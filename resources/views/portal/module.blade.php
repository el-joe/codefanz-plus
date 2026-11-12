@php
    $modules = [
        'projects' => [__('My Projects'), __('No projects yet'), __('When CodeFanz starts your project you will follow its progress, milestones and deliverables here.'), '<path d="M4 20h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.93a2 2 0 0 1-1.66-.9l-.82-1.2A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13c0 1.1.9 2 2 2Z"/>'],
        'invoices' => [__('Invoices'), __('No invoices yet'), __('Invoices and payment requests from CodeFanz will be listed here, with a secure way to pay.'), '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/>'],
        'payments' => [__('Payments'), __('No payments yet'), __('Your payment history and receipts will appear here after your first payment.'), '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/>'],
        'documents' => [__('Documents'), __('No documents yet'), __('Contracts, designs and other files shared with you will be available here.'), '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/>'],
        'conversations' => [__('Conversations'), __('No messages yet'), __('Project conversations with your CodeFanz team will appear here.'), '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>'],
        'support' => [__('Support'), __('No support tickets yet'), __('Support tickets open here once your project is live. Until then, contact us and we will help.'), '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/>'],
    ];
    [$title, $emptyTitle, $emptyDescription, $icon] = $modules[$module];
@endphp

<x-layouts.portal :title="$title" :crumb="$title">
    <x-ui.card padding="p-6" data-module="{{ $module }}">
        @include('portal._empty-module', ['title' => $emptyTitle, 'description' => $emptyDescription, 'icon' => $icon])
        <div class="flex justify-center">
            <x-ui.button variant="secondary" size="sm" :href="route('contact', ['locale' => app()->getLocale()])">{{ __('Contact CodeFanz') }}</x-ui.button>
        </div>
    </x-ui.card>
</x-layouts.portal>
