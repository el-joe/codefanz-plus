<x-ui.button variant="secondary" size="sm" :href="route('admin.payments.export', array_filter($filters))" data-payments-export>
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 3v12m0 0-4-4m4 4 4-4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg>
    {{ __('Export CSV') }}
</x-ui.button>
