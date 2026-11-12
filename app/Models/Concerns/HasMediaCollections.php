<?php

namespace App\Models\Concerns;

use App\Models\Media;
use App\Models\Mediable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * Attach library media to a model through the `mediables` pivot, grouped by collection (cover, gallery…).
 *
 * @mixin Model
 */
trait HasMediaCollections
{
    /**
     * @return MorphToMany<Media, $this>
     */
    public function mediaIn(string $collection): MorphToMany
    {
        return $this->morphToMany(Media::class, 'mediable', 'mediables')
            ->wherePivot('collection', $collection)
            ->withPivot(['collection', 'sort_order'])
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    /**
     * Replace the media of a collection with the given ids (in order).
     *
     * @param  list<int>  $mediaIds
     */
    public function syncMediaCollection(string $collection, array $mediaIds): void
    {
        Mediable::query()
            ->where('mediable_type', $this->getMorphClass())
            ->where('mediable_id', $this->getKey())
            ->where('collection', $collection)
            ->whereNotIn('media_id', $mediaIds)
            ->delete();

        foreach (array_values($mediaIds) as $position => $mediaId) {
            Mediable::query()->updateOrCreate(
                ['media_id' => $mediaId, 'mediable_type' => $this->getMorphClass(), 'mediable_id' => $this->getKey(), 'collection' => $collection],
                ['sort_order' => $position],
            );
        }
    }
}
