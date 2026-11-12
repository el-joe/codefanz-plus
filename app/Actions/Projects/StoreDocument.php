<?php

namespace App\Actions\Projects;

use App\Enums\ClientVisibility;
use App\Enums\DocumentCategory;
use App\Models\ConversationMessage;
use App\Models\Document;
use App\Models\Project;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Stores a project file on the private disk under a random name. A file whose title already exists in the
 * project becomes the next version of it. Client uploads are always visible to the client.
 */
class StoreDocument
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(
        Project $project,
        UploadedFile $file,
        ?string $title,
        DocumentCategory $category,
        ClientVisibility $visibility,
        User $uploader,
        bool $byClient = false,
        ?ConversationMessage $message = null,
    ): Document {
        $disk = (string) config('codefanz.projects.documents.disk');
        $extension = mb_strtolower($file->getClientOriginalExtension());
        $originalName = Str::limit(basename(str_replace('\\', '/', $file->getClientOriginalName())), 200, '');
        $title = Str::limit(trim((string) ($title ?: $originalName)), 160, '');

        $path = $file->storeAs(
            config('codefanz.projects.documents.directory').'/'.now()->format('Y/m'),
            Str::random(40).'.'.$extension,
            ['disk' => $disk, 'visibility' => 'private'],
        );

        try {
            $document = DB::transaction(function () use ($project, $file, $title, $category, $visibility, $uploader, $byClient, $message, $disk, $path, $originalName): Document {
                // Lock the project row so two uploads of the same title cannot pick the same version number.
                Project::query()->whereKey($project->getKey())->lockForUpdate()->first();
                $version = (int) Document::query()->where('project_id', $project->getKey())->where('title', $title)->max('version') + 1;

                $document = Document::create([
                    'uuid' => (string) Str::uuid(),
                    'project_id' => $project->getKey(),
                    'customer_id' => $project->customer_id,
                    'conversation_message_id' => $message?->getKey(),
                    'title' => $title,
                    'category' => $category,
                    'visibility' => $byClient ? ClientVisibility::Client : $visibility,
                    'version' => $version,
                    'disk' => $disk,
                    'path' => $path,
                    'original_name' => $originalName,
                    'mime' => (string) $file->getMimeType(),
                    'size' => (int) $file->getSize(),
                    'uploaded_by' => $uploader->getKey(),
                    'uploaded_by_client' => $byClient,
                ]);

                $this->audit->record(
                    operation: 'document.uploaded',
                    auditable: $document,
                    user: $uploader,
                    after: [
                        'project' => $project->reference,
                        'title' => $document->title,
                        'version' => $document->version,
                        'visibility' => $document->visibility->value,
                        'by_client' => $byClient,
                        'size' => $document->size,
                    ],
                );

                return $document;
            });
        } catch (Throwable $exception) {
            Storage::disk($disk)->delete($path);

            throw $exception;
        }

        return $document;
    }
}
