<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['tag_id', 'locale', 'name', 'slug'])]
class TagTranslation extends Model
{
    //
}
