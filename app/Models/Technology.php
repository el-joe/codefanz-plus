<?php

namespace App\Models;

use App\Enums\TechnologyProficiency;
use App\Models\Concerns\HasTranslations;
use Database\Factories\TechnologyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A technology shown on the public site.
 *
 * The `proficiency` column holds the level an admin assigned (Supported or Expertise). The level shown
 * publicly comes from proficiency(), which upgrades to VerifiedInProjects when any registered verification
 * relation (e.g. published portfolio projects, added by the portfolio module via verifiedThrough()) has rows.
 *
 * @method TechnologyTranslation|null translation(?string $locale = null)
 * @method TechnologyTranslation|null exactTranslation(string $locale)
 */
#[Fillable(['technology_category_id', 'key', 'icon', 'website_url', 'proficiency', 'is_visible', 'sort_order'])]
class Technology extends Model
{
    /** @use HasFactory<TechnologyFactory> */
    use HasFactory, HasTranslations, SoftDeletes;

    public const TRANSLATED_FIELDS = ['name', 'description'];

    /**
     * Relations whose existence proves delivered-project experience. Each must already be constrained to
     * published records, e.g. `publishedPortfolioProjects`.
     *
     * @var list<string>
     */
    protected static array $verificationRelations = [];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'proficiency' => 'supported',
        'is_visible' => true,
        'sort_order' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'proficiency' => TechnologyProficiency::class,
            'is_visible' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Register a relation that, when it has rows, marks a technology as verified in projects.
     */
    public static function verifiedThrough(string $relation): void
    {
        if (! in_array($relation, static::$verificationRelations, true)) {
            static::$verificationRelations[] = $relation;
        }
    }

    /**
     * @return list<string>
     */
    public static function verificationRelations(): array
    {
        return static::$verificationRelations;
    }

    public static function flushVerificationRelations(): void
    {
        static::$verificationRelations = [];
    }

    /**
     * Add `verified_via_{relation}` existence flags so proficiency() runs without extra queries.
     *
     * @param  Builder<Technology>  $query
     */
    public function scopeWithProficiency(Builder $query): void
    {
        foreach (static::$verificationRelations as $relation) {
            $query->withExists("{$relation} as ".self::verificationAttribute($relation));
        }
    }

    /**
     * @param  Builder<Technology>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('id');
    }

    public function isVerifiedInProjects(): bool
    {
        foreach (static::$verificationRelations as $relation) {
            $attribute = self::verificationAttribute($relation);

            $verified = array_key_exists($attribute, $this->attributes)
                ? (bool) $this->attributes[$attribute]
                : $this->{$relation}()->exists();

            if ($verified) {
                return true;
            }
        }

        return false;
    }

    /**
     * The level shown to visitors: computed Verified, otherwise the admin-assigned level.
     */
    public function proficiency(): TechnologyProficiency
    {
        if ($this->isVerifiedInProjects()) {
            return TechnologyProficiency::VerifiedInProjects;
        }

        return $this->getAttribute('proficiency') === TechnologyProficiency::Expertise
            ? TechnologyProficiency::Expertise
            : TechnologyProficiency::Supported;
    }

    /**
     * @return BelongsTo<TechnologyCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(TechnologyCategory::class, 'technology_category_id');
    }

    /**
     * @return BelongsToMany<Service, $this>
     */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class)->withTimestamps();
    }

    /**
     * @return BelongsToMany<PortfolioProject, $this>
     */
    public function portfolioProjects(): BelongsToMany
    {
        return $this->belongsToMany(PortfolioProject::class, 'portfolio_project_technology')->withTimestamps();
    }

    /**
     * Published, publicly listed (non-trashed) projects: registered as the verification relation in AppServiceProvider.
     *
     * @return BelongsToMany<PortfolioProject, $this>
     */
    public function publishedPortfolioProjects(): BelongsToMany
    {
        return $this->portfolioProjects()->publiclyListed();
    }

    private static function verificationAttribute(string $relation): string
    {
        return 'verified_via_'.str($relation)->snake();
    }
}
