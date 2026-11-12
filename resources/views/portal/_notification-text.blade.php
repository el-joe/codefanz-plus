{{-- One portal notification sentence, rendered in the reader's language from the stored keys. Milestone 6 adds kinds here. --}}
{{ match ($notification->data['kind'] ?? null) {
    default => __('Notification'),
} }}
