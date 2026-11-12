<?php

namespace App\Models;

use App\Enums\ClientVisibility;
use Database\Factories\ProjectNoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A project note: `internal` notes stay in the admin, `client` notes are updates shown in the portal.
 */
#[Fillable(['project_id', 'user_id', 'visibility', 'body'])]
class ProjectNote extends Model
{
    /** @use HasFactory<ProjectNoteFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'visibility' => ClientVisibility::class,
        ];
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
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
