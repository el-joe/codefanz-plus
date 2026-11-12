<?php

namespace App\Models;

use App\Enums\BudgetRange;
use App\Enums\ContactMethod;
use App\Enums\LeadPriority;
use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Enums\ProjectTimeline;
use Database\Factories\LeadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A sales lead. Admin URLs use the ulid; people see the reference (LD-2026-00042).
 */
#[Fillable([
    'source', 'status', 'priority', 'assigned_to', 'name', 'email', 'phone', 'company', 'country',
    'preferred_locale', 'preferred_contact', 'platforms', 'description', 'features', 'goals', 'existing_url',
    'budget_range', 'timeline', 'deadline', 'brief', 'estimated_value_minor', 'currency', 'follow_up_at',
    'lost_reason', 'contact_message_id', 'possible_duplicate_of_id', 'locale', 'utm_source', 'utm_medium',
    'utm_campaign', 'referrer', 'ip_address', 'user_agent', 'consent_at', 'created_by', 'updated_by',
])]
class Lead extends Model
{
    /** @use HasFactory<LeadFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    /**
     * Mirror database defaults so a freshly created instance reports the correct values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'new',
        'priority' => 'normal',
        'preferred_contact' => 'email',
        'lock_version' => 1,
    ];

    protected static function booted(): void
    {
        static::created(function (Lead $lead): void {
            $lead->forceFill(['reference' => self::referenceFor($lead)])->saveQuietly();
        });
    }

    public static function referenceFor(Lead $lead): string
    {
        return sprintf('LD-%d-%05d', $lead->created_at?->year ?? now()->year, $lead->getKey());
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
            'source' => LeadSource::class,
            'status' => LeadStatus::class,
            'priority' => LeadPriority::class,
            'preferred_contact' => ContactMethod::class,
            'budget_range' => BudgetRange::class,
            'timeline' => ProjectTimeline::class,
            'platforms' => 'array',
            'brief' => 'array',
            'deadline' => 'date',
            'follow_up_at' => 'datetime',
            'consent_at' => 'datetime',
            'estimated_value_minor' => 'integer',
            'lock_version' => 'integer',
        ];
    }

    /**
     * @param  Builder<Lead>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', LeadStatus::open());
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<ContactMessage, $this>
     */
    public function contactMessage(): BelongsTo
    {
        return $this->belongsTo(ContactMessage::class);
    }

    /**
     * @return BelongsTo<Lead, $this>
     */
    public function possibleDuplicateOf(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'possible_duplicate_of_id');
    }

    /**
     * @return BelongsToMany<Service, $this>
     */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class);
    }

    /**
     * @return BelongsToMany<Technology, $this>
     */
    public function technologies(): BelongsToMany
    {
        return $this->belongsToMany(Technology::class);
    }

    /**
     * The customer account this lead was converted into, if any.
     *
     * @return HasOne<Customer, $this>
     */
    public function customer(): HasOne
    {
        return $this->hasOne(Customer::class, 'created_from_lead_id');
    }

    /**
     * The AI Project Assistant conversation this lead came from, while it is retained.
     *
     * @return HasOne<ChatSession, $this>
     */
    public function chatSession(): HasOne
    {
        return $this->hasOne(ChatSession::class);
    }

    /**
     * @return HasMany<LeadActivity, $this>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(LeadActivity::class);
    }

    /**
     * @return HasMany<LeadAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(LeadAttachment::class);
    }

    /**
     * Estimated value formatted for display, e.g. "USD 12,000".
     */
    public function formattedEstimatedValue(): ?string
    {
        if ($this->estimated_value_minor === null) {
            return null;
        }

        return ($this->currency ?? 'USD').' '.number_format($this->estimated_value_minor / 100, 0);
    }
}
