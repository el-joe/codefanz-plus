<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['service_feature_id', 'locale', 'title', 'description'])]
class ServiceFeatureTranslation extends Model
{
    //
}
