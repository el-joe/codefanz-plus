<?php

namespace App\Actions\Contact;

use App\Actions\Leads\CreateLead;
use App\Enums\ActorType;
use App\Enums\ContactTopic;
use App\Enums\LeadSource;
use App\Models\ContactMessage;
use App\Models\Language;
use App\Notifications\ContactMessageReceived;
use App\Services\AuditLogger;
use App\Services\Settings;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Stores a contact-form message, audits it, emails the contact address from Settings and turns it into
 * a CRM lead (source contact_form). Without a configured contact address the message still lands in the inbox.
 */
class ReceiveContactMessage
{
    public function __construct(private AuditLogger $audit, private Settings $settings, private CreateLead $createLead) {}

    /**
     * @param  array{name: string, email: string, phone?: string|null, topic: string, message: string, preferred_locale: string}  $data
     */
    public function handle(array $data, ?string $ipAddress, ?string $userAgent): ContactMessage
    {
        $message = ContactMessage::create([
            'name' => trim($data['name']),
            'email' => mb_strtolower(trim($data['email'])),
            'phone' => filled($data['phone'] ?? null) ? trim($data['phone']) : null,
            'topic' => ContactTopic::from($data['topic']),
            'message' => trim($data['message']),
            'preferred_locale' => $data['preferred_locale'],
            'locale' => app()->getLocale(),
            'consented_at' => now(),
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent !== null ? mb_substr($userAgent, 0, 255) : null,
        ]);

        // Personal data stays out of the audit trail; the entry only records that a message arrived.
        $this->audit->record(
            operation: 'contact_message.received',
            auditable: $message,
            actorType: ActorType::System,
            actorLabel: 'Contact form',
            after: ['topic' => $message->topic->value, 'preferred_locale' => $message->preferred_locale],
        );

        $recipient = $this->settings->get('contact', 'email');

        if (filled($recipient)) {
            Notification::route('mail', $recipient)->notify((new ContactMessageReceived($message))->locale(Language::defaultCode()));
        } else {
            Log::warning('Contact message received but no contact email is configured.', ['contact_message_id' => $message->id]);
        }

        $this->createLead->handle(self::leadData($message), LeadSource::ContactForm, contactMessage: $message, sendAutoReply: true);

        return $message;
    }

    /**
     * Lead attributes for a contact message (also used by the backfill command).
     *
     * @return array<string, mixed>
     */
    public static function leadData(ContactMessage $message): array
    {
        return [
            'name' => $message->name,
            'email' => $message->email,
            'phone' => $message->phone,
            'preferred_locale' => $message->preferred_locale,
            'description' => $message->message,
            'brief' => ['topic' => $message->topic->value],
            'locale' => $message->locale,
            'ip_address' => $message->ip_address,
            'user_agent' => $message->user_agent,
            'consent_at' => $message->consented_at,
        ];
    }
}
