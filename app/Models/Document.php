<?php

namespace App\Models;

use App\Enums\ClientVisibility;
use App\Enums\DocumentCategory;
use App\Models\Concerns\BelongsToCustomer;
use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Number;

/**
 * A project file on the private disk under a random name. Only downloadable through the authorized admin and
 * portal controllers. Uploading a file with an existing title in the same project stores the next version.
 */
#[Fillable([
    'uuid', 'project_id', 'customer_id', 'conversation_message_id', 'title', 'category', 'visibility', 'version',
    'disk', 'path', 'original_name', 'mime', 'size', 'uploaded_by', 'uploaded_by_client',
])]
class Document extends Model
{
    /** @use HasFactory<DocumentFactory> */
    use BelongsToCustomer, HasFactory;

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => DocumentCategory::class,
            'visibility' => ClientVisibility::class,
            'version' => 'integer',
            'size' => 'integer',
            'uploaded_by_client' => 'boolean',
        ];
    }

    /**
     * @param  Builder<Document>  $query
     */
    public function scopeClientVisible(Builder $query): void
    {
        $query->where('visibility', ClientVisibility::Client);
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function humanSize(): string
    {
        return Number::fileSize($this->size, precision: 1);
    }

    public function isClientVisible(): bool
    {
        return $this->visibility === ClientVisibility::Client;
    }
}
