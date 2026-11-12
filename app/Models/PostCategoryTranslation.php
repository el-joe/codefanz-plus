<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['post_category_id', 'locale', 'name', 'slug', 'description'])]
class PostCategoryTranslation extends Model
{
    //
}
