<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['technology_id', 'locale', 'name', 'description'])]
class TechnologyTranslation extends Model
{
    //
}
