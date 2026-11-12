<?php

namespace App\Console\Commands;

use App\Actions\Contact\ReceiveContactMessage;
use App\Actions\Leads\CreateLead;
use App\Enums\LeadSource;
use App\Models\ContactMessage;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * One-off: turns contact messages received before Milestone 5 into leads. Safe to re-run — messages
 * that already have a lead are skipped. No notifications, events or auto-replies are sent.
 */
#[Signature('leads:backfill-contact-messages')]
#[Description('Create a lead for every contact message that does not have one yet')]
class BackfillContactMessageLeads extends Command
{
    public function handle(CreateLead $createLead): int
    {
        $created = 0;

        ContactMessage::query()->doesntHave('lead')->orderBy('id')->chunkById(200, function ($messages) use ($createLead, &$created): void {
            foreach ($messages as $message) {
                $createLead->handle(
                    [...ReceiveContactMessage::leadData($message), 'created_at' => $message->created_at],
                    LeadSource::ContactForm,
                    contactMessage: $message,
                    notify: false,
                );
                $created++;
            }
        });

        $this->components->info("Created {$created} lead(s) from contact messages.");

        return self::SUCCESS;
    }
}
