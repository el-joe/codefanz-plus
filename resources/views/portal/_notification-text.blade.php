{{-- One portal notification sentence, rendered in the reader's language from the stored keys. --}}
{{ match ($notification->data['kind'] ?? null) {
    'conversation.message' => __(':name sent a message about :project', ['name' => $notification->data['name'] ?? '', 'project' => $notification->data['project_name'] ?? '']),
    'milestone.awaiting_approval' => __('Milestone ready for your review: :title', ['title' => $notification->data['title'] ?? '']),
    'ticket.replied' => __('New reply on ticket :reference', ['reference' => $notification->data['reference'] ?? '']),
    'ticket.status' => __('Ticket :reference is now :status', ['reference' => $notification->data['reference'] ?? '', 'status' => \App\Enums\TicketStatus::tryFrom($notification->data['status'] ?? '')?->clientLabel() ?? '']),
    default => __('Notification'),
} }}
