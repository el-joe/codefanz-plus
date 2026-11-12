<?php

namespace App\Actions\Projects;

use App\Models\Conversation;
use App\Models\Project;
use App\Models\User;
use App\Services\AuditLogger;

/**
 * Opens a new message thread on a project (staff from the admin, clients from the portal).
 */
class StartConversation
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(Project $project, string $subject, User $actor): Conversation
    {
        $conversation = $project->conversations()->create([
            'customer_id' => $project->customer_id,
            'subject' => trim($subject),
            'created_by' => $actor->getKey(),
        ]);

        $this->audit->record(
            operation: 'conversation.created',
            auditable: $conversation,
            user: $actor,
            after: ['project' => $project->reference, 'subject' => $conversation->subject],
        );

        return $conversation;
    }
}
