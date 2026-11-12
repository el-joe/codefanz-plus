<?php

namespace App\Models;

use App\Contracts\Approvable;
use App\Enums\PageStatus;
use App\Enums\Permission;
use Database\Factories\PageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['template', 'status', 'published_at', 'created_by', 'updated_by'])]
class Page extends Model implements Approvable
{
    /** @use HasFactory<PageFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Mirror database defaults so a freshly created instance reports the correct version.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'lock_version' => 1,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PageStatus::class,
            'lock_version' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<PageTranslation, $this>
     */
    public function translations(): HasMany
    {
        return $this->hasMany(PageTranslation::class);
    }

    public function translation(?string $locale = null): ?PageTranslation
    {
        return $this->translations->firstWhere('locale', $locale ?? app()->getLocale());
    }

    public function approvableVersion(): int
    {
        return (int) $this->lock_version;
    }

    public function approvableAttributes(): array
    {
        return ['title', 'slug', 'body', 'seo_title', 'seo_description'];
    }

    public function approvableSnapshot(?string $locale): array
    {
        $translation = $this->translations()->where('locale', $locale)->first();

        return $translation?->only($this->approvableAttributes()) ?? [];
    }

    public function applyApprovedAttributes(array $attributes, ?string $locale): void
    {
        $this->translations()->updateOrCreate(['locale' => $locale], $attributes);

        $this->forceFill(['lock_version' => $this->lock_version + 1])->save();
    }

    public function approvalPermission(array $attributes): Permission
    {
        $seoAttributes = ['seo_title', 'seo_description'];

        return array_diff(array_keys($attributes), $seoAttributes) === []
            ? Permission::SeoApprove
            : Permission::ContentApprove;
    }
}
