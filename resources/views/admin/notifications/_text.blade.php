{{-- One notification sentence, rendered in the reader's language from the stored keys. --}}
@switch($notification->data['kind'] ?? null)
    @case('lead.created')
        {{ __('New lead :reference from :name', ['reference' => $notification->data['reference'] ?? '', 'name' => $notification->data['name'] ?? '']) }}
        @break
    @case('lead.assigned')
        {{ __('Lead :reference was assigned to you', ['reference' => $notification->data['reference'] ?? '']) }}
        @break
    @default
        {{ __('Notification') }}
@endswitch
