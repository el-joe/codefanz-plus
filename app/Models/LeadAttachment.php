<?php

namespace App\Models;

use Database\Factories\LeadAttachmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Number;

/**
 * A file sent with a lead. Stored on the private disk under a random name; downloaded only through
 * the authorized admin controller.
 */
#[Fillable(['uuid', 'lead_id', 'disk', 'path', 'original_name', 'mime', 'size', 'uploaded_by'])]
class LeadAttachment extends Model
{
    /** @use HasFactory<LeadAttachmentFactory> */
    use HasFactory;

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
            'size' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function humanSize(): string
    {
        return Number::fileSize($this->size, precision: 1);
    }
}
