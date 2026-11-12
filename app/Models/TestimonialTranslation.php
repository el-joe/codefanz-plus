<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['testimonial_id', 'locale', 'quote', 'author_role'])]
class TestimonialTranslation extends Model
{
    //
}
