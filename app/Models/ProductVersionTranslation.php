<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['product_version_id', 'locale', 'changelog'])]
class ProductVersionTranslation extends Model
{
    //
}
