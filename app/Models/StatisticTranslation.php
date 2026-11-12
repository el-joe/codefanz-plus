<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['statistic_id', 'locale', 'label', 'suffix'])]
class StatisticTranslation extends Model
{
    //
}
