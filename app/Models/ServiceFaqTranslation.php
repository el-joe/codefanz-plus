<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['service_faq_id', 'locale', 'question', 'answer'])]
class ServiceFaqTranslation extends Model
{
    //
}
