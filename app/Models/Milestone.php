<?php

namespace App\Models;

use App\Enums\MilestoneStatus;
use App\Services\Money;
use Database\Factories\MilestoneFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

/**
 * A project milestone. `paid_minor` is a cache derived from the payments ledger (M6.2) and is never filled from forms.
 */
#[Fillable(['project_id', 'sort_order', 'title', 'description', 'due_date', 'status', 'amount_minor', 'currency'])]
class Milestone extends Model
{
    /** @use HasFactory<MilestoneFactory> */
    use HasFactory, HasUlids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
        'amount_minor' => 0,
        'paid_minor' => 0,
        'currency' => 'USD',
        'sort_order' => 0,
        'lock_version' => 1,
    ];

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
            'status' => MilestoneStatus::class,
            'due_date' => 'date',
            'amount_minor' => 'integer',
            'paid_minor' => 'integer',
            'sort_order' => 'integer',
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'completed_at' => 'datetime',
            'lock_version' => 'integer',
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
     * Milestones that must be completed before this one can start.
     *
     * @return BelongsToMany<Milestone, $this>
     */
    public function dependencies(): BelongsToMany
    {
        return $this->belongsToMany(Milestone::class, 'milestone_dependencies', 'milestone_id', 'depends_on_id')->withTimestamps();
    }

    /**
     * @return BelongsToMany<Milestone, $this>
     */
    public function dependents(): BelongsToMany
    {
        return $this->belongsToMany(Milestone::class, 'milestone_dependencies', 'depends_on_id', 'milestone_id')->withTimestamps();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Dependencies that are not completed yet (requires `dependencies` loaded).
     *
     * @return Collection<int, Milestone>
     */
    public function blockingDependencies(): Collection
    {
        return $this->dependencies->reject(fn (Milestone $dependency): bool => $dependency->status === MilestoneStatus::Completed)->values();
    }

    public function formattedAmount(): string
    {
        return Money::format($this->amount_minor, $this->currency);
    }

    public function remainingMinor(): int
    {
        return max(0, $this->amount_minor - $this->paid_minor);
    }
}
