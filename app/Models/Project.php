<?php

namespace App\Models;

use App\Enums\ClientVisibility;
use App\Enums\MilestoneStatus;
use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use App\Models\Concerns\BelongsToCustomer;
use App\Services\Money;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A delivery project for a customer. Admin and portal URLs use the ulid; people see the reference (PR-2026-00042).
 * Portal code must query through forCurrentCustomer().
 */
#[Fillable([
    'customer_id', 'lead_id', 'name', 'description', 'type', 'status', 'start_date', 'due_date',
    'project_manager_id', 'budget_minor', 'currency', 'show_budget_to_client', 'show_team_to_client',
    'created_by', 'updated_by',
])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use BelongsToCustomer, HasFactory, HasUlids, SoftDeletes;

    /**
     * Mirror database defaults so a freshly created instance reports the correct values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'type' => 'web',
        'status' => 'planning',
        'currency' => 'USD',
        'show_budget_to_client' => true,
        'show_team_to_client' => true,
        'lock_version' => 1,
    ];

    protected static function booted(): void
    {
        static::created(function (Project $project): void {
            $project->forceFill(['reference' => sprintf('PR-%d-%05d', $project->created_at?->year ?? now()->year, $project->getKey())])->saveQuietly();
        });
    }

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ProjectType::class,
            'status' => ProjectStatus::class,
            'start_date' => 'date',
            'due_date' => 'date',
            'budget_minor' => 'integer',
            'show_budget_to_client' => 'boolean',
            'show_team_to_client' => 'boolean',
            'lock_version' => 'integer',
        ];
    }

    /**
     * @param  Builder<Project>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereIn('status', ProjectStatus::active());
    }

    /**
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'project_manager_id');
    }

    /**
     * Staff working on the project (role is a free-text label such as "Backend lead").
     *
     * @return BelongsToMany<User, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_members')->withPivot('role')->withTimestamps();
    }

    /**
     * @return HasMany<Milestone, $this>
     */
    public function milestones(): HasMany
    {
        return $this->hasMany(Milestone::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return HasMany<ProjectNote, $this>
     */
    public function notes(): HasMany
    {
        return $this->hasMany(ProjectNote::class);
    }

    /**
     * Updates the client may read. Internal notes never leave the admin.
     *
     * @return HasMany<ProjectNote, $this>
     */
    public function clientUpdates(): HasMany
    {
        return $this->hasMany(ProjectNote::class)->where('visibility', ClientVisibility::Client);
    }

    /**
     * @return HasMany<Document, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /**
     * @return HasMany<Conversation, $this>
     */
    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    /**
     * @return HasMany<SupportTicket, $this>
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Share of milestones approved or completed, 0–100 (requires `milestones` loaded).
     */
    public function progressPercent(): int
    {
        $total = $this->milestones->count();

        if ($total === 0) {
            return $this->status === ProjectStatus::Completed ? 100 : 0;
        }

        return (int) round($this->milestones->filter(fn (Milestone $milestone): bool => $milestone->status->isDone())->count() / $total * 100);
    }

    /**
     * The first milestone that is not finished yet (requires `milestones` loaded).
     */
    public function nextMilestone(): ?Milestone
    {
        return $this->milestones->first(fn (Milestone $milestone): bool => ! $milestone->status->isDone());
    }

    public function formattedBudget(): ?string
    {
        return $this->budget_minor === null ? null : Money::format($this->budget_minor, $this->currency);
    }

    /**
     * Total of milestone amounts (requires `milestones` loaded).
     */
    public function milestonesTotalMinor(): int
    {
        return (int) $this->milestones->sum('amount_minor');
    }

    public function milestoneCount(MilestoneStatus $status): int
    {
        return $this->milestones->where('status', $status)->count();
    }
}
