<?php

namespace App\Http\Requests\Admin\Concerns;

use App\Enums\MediaStatus;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Only optimized (ready), non-trashed library images may be attached as covers, galleries or screenshots.
 */
trait ValidatesLibraryImages
{
    protected function libraryImageRule(): Exists
    {
        return Rule::exists('media', 'id')
            ->where('status', MediaStatus::Ready->value)
            ->where(fn ($query) => $query->where('mime_type', 'like', 'image/%'))
            ->whereNull('deleted_at');
    }

    /**
     * Drop the blank selects the gallery picker always submits.
     *
     * @return list<string>
     */
    protected function filledIds(string $key): array
    {
        return array_values(array_filter((array) $this->input($key, []), fn (mixed $id): bool => filled($id)));
    }
}
