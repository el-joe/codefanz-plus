{{-- One notification sentence, rendered in the reader's language from the stored keys. --}}
@switch($notification->data['kind'] ?? null)
    @case('lead.created')
        {{ __('New lead :reference from :name', ['reference' => $notification->data['reference'] ?? '', 'name' => $notification->data['name'] ?? '']) }}
        @break
    @case('lead.assigned')
        {{ __('Lead :reference was assigned to you', ['reference' => $notification->data['reference'] ?? '']) }}
        @break
    @case('conversation.message')
        {{ __(':name sent a message about :project', ['name' => $notification->data['name'] ?? '', 'project' => $notification->data['project_name'] ?? '']) }}
        @break
    @case('milestone.approved')
        {{ __(':name approved “:title”', ['name' => $notification->data['name'] ?? '', 'title' => $notification->data['title'] ?? '']) }}
        @break
    @case('milestone.changes_requested')
        {{ __(':name requested changes to “:title”', ['name' => $notification->data['name'] ?? '', 'title' => $notification->data['title'] ?? '']) }}
        @break
    @case('ticket.opened')
        {{ __('New support ticket :reference', ['reference' => $notification->data['reference'] ?? '']) }}
        @break
    @case('ticket.replied')
        {{ __('New reply on ticket :reference', ['reference' => $notification->data['reference'] ?? '']) }}
        @break
    @case('ticket.status')
        {{ __('Ticket :reference was updated by :name', ['reference' => $notification->data['reference'] ?? '', 'name' => $notification->data['name'] ?? '']) }}
        @break
    @case('payment.received')
        {{ __('Payment :reference received: :amount', ['reference' => $notification->data['reference'] ?? '', 'amount' => \App\Services\Money::localized((int) ($notification->data['amount_minor'] ?? 0), (string) ($notification->data['currency'] ?? 'USD'))]) }}
        @break
    @default
        {{ __('Notification') }}
@endswitch
