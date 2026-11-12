<?php

namespace App\Actions\Projects;

use App\Enums\ClientVisibility;
use App\Models\Project;
use App\Models\ProjectNote;
use App\Models\User;
use App\Services\AuditLogger;

/**
 * Staff add an internal note (team only) or a client-visible update (shown in the portal).
 */
class AddProjectNote
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(Project $project, ClientVisibility $visibility, string $body, User $actor): ProjectNote
    {
        $note = $project->notes()->create([
            'user_id' => $actor->getKey(),
            'visibility' => $visibility,
            'body' => trim($body),
        ]);

        // The body stays out of the audit trail; the note itself is the record.
        $this->audit->record(
            operation: 'project.note_added',
            auditable: $note,
            user: $actor,
            after: ['project' => $project->reference, 'visibility' => $visibility->value],
        );

        return $note;
    }
}
