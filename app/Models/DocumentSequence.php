<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Counter row per document type and year. Read with SELECT … FOR UPDATE through App\Services\DocumentNumbers.
 */
#[Fillable(['type', 'year', 'next_value'])]
class DocumentSequence extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['year' => 'integer', 'next_value' => 'integer'];
    }
}
