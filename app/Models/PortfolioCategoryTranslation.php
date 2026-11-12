<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['portfolio_category_id', 'locale', 'name', 'slug'])]
class PortfolioCategoryTranslation extends Model
{
    //
}
