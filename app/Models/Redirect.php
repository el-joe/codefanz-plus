<?php

namespace App\Models;

use Database\Factories\RedirectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A managed redirect checked before a 404 is served. `from_path` is a normalized site path; `to_path` is a site
 * path or an absolute https URL (null for 410 Gone).
 */
#[Fillable(['from_path', 'to_path', 'status_code', 'is_active', 'is_automatic', 'note', 'created_by', 'updated_by'])]
class Redirect extends Model
{
    /** @use HasFactory<RedirectFactory> */
    use HasFactory;

    public const STATUS_CODES = [301, 302, 410];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status_code' => 301,
        'is_active' => true,
        'is_automatic' => false,
        'hits' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status_code' => 'integer',
            'hits' => 'integer',
            'is_active' => 'boolean',
            'is_automatic' => 'boolean',
            'last_hit_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
