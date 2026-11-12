<x-ui.alert tone="warning" :title="__('Financial controls')" data-financial-controls>
    {{ __('Payments are marked paid only after the gateway confirms them server-side. Every refund needs approval by a second finance user, and payment-credential changes need a confirmed password. AI agents and MCP clients cannot initiate refunds or transfers.') }}
</x-ui.alert>
