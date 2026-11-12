<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Database\Factories\ServiceFaqFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ordered child row of a service, edited inside the service form (translated: question, answer).
 *
 * @method ServiceFaqTranslation|null translation(?string $locale = null)
 * @method ServiceFaqTranslation|null exactTranslation(string $locale)
 */
#[Fillable(['service_id', 'sort_order'])]
class ServiceFaq extends Model
{
    /** @use HasFactory<ServiceFaqFactory> */
    use HasFactory, HasTranslations;

    public const TRANSLATED_FIELDS = ['question', 'answer'];

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
