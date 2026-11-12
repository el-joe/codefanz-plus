<?php

namespace App\Actions\Projects;

use App\Models\Document;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Storage;

/**
 * Removes one document version and its stored file (staff only).
 */
class DeleteDocument
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(Document $document, User $actor): void
    {
        $document->delete();
        Storage::disk($document->disk)->delete($document->path);

        $this->audit->record(
            operation: 'document.deleted',
            auditable: $document,
            user: $actor,
            before: ['title' => $document->title, 'version' => $document->version, 'visibility' => $document->visibility->value],
        );
    }
}
