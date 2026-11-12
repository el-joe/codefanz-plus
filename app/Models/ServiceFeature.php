<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Database\Factories\ServiceFeatureFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ordered child row of a service, edited inside the service form (translated: title, description).
 *
 * @method ServiceFeatureTranslation|null translation(?string $locale = null)
 * @method ServiceFeatureTranslation|null exactTranslation(string $locale)
 */
#[Fillable(['service_id', 'sort_order'])]
class ServiceFeature extends Model
{
    /** @use HasFactory<ServiceFeatureFactory> */
    use HasFactory, HasTranslations;

    public const TRANSLATED_FIELDS = ['title', 'description'];

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
