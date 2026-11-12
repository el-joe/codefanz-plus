<?php

namespace App\Actions\Leads;

use App\Enums\ActorType;
use App\Enums\LeadActivityType;
use App\Enums\LeadSource;
use App\Events\LeadCreated;
use App\Models\ContactMessage;
use App\Models\Lead;
use App\Models\LeadAttachment;
use App\Models\User;
use App\Notifications\LeadReceivedConfirmation;
use App\Notifications\NewLeadReceived;
use App\Services\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Creates a lead from any source: stores private attachments, flags a possible duplicate, writes the
 * first timeline entry and the audit record, then (after commit) fires LeadCreated, notifies the team
 * and sends the visitor an auto-reply in their language.
 */
class CreateLead
{
    /**
     * Columns copied straight from the input when present.
     *
     * @var list<string>
     */
    private const COLUMNS = [
        'name', 'email', 'phone', 'company', 'country', 'preferred_locale', 'preferred_contact', 'platforms',
        'description', 'features', 'goals', 'existing_url', 'budget_range', 'timeline', 'deadline', 'brief',
        'priority', 'assigned_to', 'follow_up_at', 'estimated_value_minor', 'currency', 'locale', 'utm_source',
        'utm_medium', 'utm_campaign', 'referrer', 'ip_address', 'user_agent', 'consent_at',
    ];

    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  list<UploadedFile>  $attachments
     * @param  bool  $notify  false for imports/backfills: no events, notifications or auto-reply
     * @param  bool  $sendAutoReply  confirm receipt to the visitor (public forms only)
     */
    public function handle(
        array $data,
        LeadSource $source,
        array $attachments = [],
        ?User $creator = null,
        ?ContactMessage $contactMessage = null,
        bool $notify = true,
        bool $sendAutoReply = false,
    ): Lead {
        $stored = [];

        try {
            $lead = DB::transaction(function () use ($data, $source, $attachments, $creator, $contactMessage, &$stored): Lead {
                $attributes = Arr::only($data, self::COLUMNS);
                $attributes['email'] = mb_strtolower(trim((string) $attributes['email']));
                $attributes['name'] = trim((string) $attributes['name']);

                $lead = new Lead([
                    ...$attributes,
                    'source' => $source,
                    'contact_message_id' => $contactMessage?->getKey(),
                    'possible_duplicate_of_id' => Lead::query()->open()->where('email', $attributes['email'])->latest('id')->value('id'),
                    'created_by' => $creator?->getKey(),
                    'updated_by' => $creator?->getKey(),
                ]);

                if (isset($data['created_at'])) {
                    $lead->created_at = $data['created_at'];
                }

                $lead->save();
                $lead->services()->sync(array_map('intval', (array) ($data['services'] ?? [])));
                $lead->technologies()->sync(array_map('intval', (array) ($data['technologies'] ?? [])));

                foreach ($attachments as $file) {
                    $stored[] = $this->storeAttachment($lead, $file, $creator);
                }

                $lead->activities()->create([
                    'type' => LeadActivityType::System,
                    'body' => null,
                    'meta' => ['event' => 'created', 'source' => $source->value, 'attachments' => count($attachments)],
                    'user_id' => $creator?->getKey(),
                ]);

                // Personal data stays out of the audit trail.
                $this->audit->record(
                    operation: 'lead.created',
                    auditable: $lead,
                    user: $creator,
                    actorType: $creator ? ActorType::User : ActorType::System,
                    actorLabel: $creator ? null : $source->label(),
                    after: [
                        'reference' => $lead->reference,
                        'source' => $source->value,
                        'status' => $lead->status->value,
                        'assigned_to' => $lead->assigned_to,
                        'attachments' => count($attachments),
                        'possible_duplicate_of_id' => $lead->possible_duplicate_of_id,
                    ],
                );

                return $lead;
            });
        } catch (Throwable $exception) {
            foreach ($stored as $attachment) {
                Storage::disk($attachment->disk)->delete($attachment->path);
            }

            throw $exception;
        }

        if ($notify) {
            LeadCreated::dispatch($lead);
            $this->notifyTeam($lead, $creator);

            if ($sendAutoReply) {
                Notification::route('mail', [$lead->email => $lead->name])
                    ->notify((new LeadReceivedConfirmation($lead))->locale($lead->preferred_locale));
            }
        }

        return $lead;
    }

    /**
     * Everyone who can manage leads, plus the assignee when one was set at creation, hears about a new lead.
     */
    private function notifyTeam(Lead $lead, ?User $creator): void
    {
        /** @var Collection<int, User> $recipients */
        $recipients = User::query()
            ->where('is_active', true)
            ->where(fn ($query) => $query
                ->whereIn('id', User::query()->leadManagers()->select('id'))
                ->when($lead->assigned_to, fn ($query) => $query->orWhereKey($lead->assigned_to)))
            ->when($creator, fn ($query) => $query->whereKeyNot($creator->getKey()))
            ->get();

        Notification::send($recipients, new NewLeadReceived($lead));
    }

    private function storeAttachment(Lead $lead, UploadedFile $file, ?User $uploader): LeadAttachment
    {
        $disk = config('codefanz.leads.attachments.disk');
        $extension = mb_strtolower($file->getClientOriginalExtension());
        // Random name; the visitor's file name is only kept as metadata for the download header.
        $path = $file->storeAs(
            config('codefanz.leads.attachments.directory').'/'.now()->format('Y/m'),
            Str::random(40).'.'.$extension,
            ['disk' => $disk, 'visibility' => 'private'],
        );

        return $lead->attachments()->create([
            'uuid' => (string) Str::uuid(),
            'disk' => $disk,
            'path' => $path,
            'original_name' => Str::limit(basename(str_replace('\\', '/', $file->getClientOriginalName())), 200, ''),
            'mime' => (string) $file->getMimeType(),
            'size' => (int) $file->getSize(),
            'uploaded_by' => $uploader?->getKey(),
        ]);
    }
}
