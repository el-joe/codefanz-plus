<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['menu_item_id', 'locale', 'label'])]
class MenuItemTranslation extends Model
{
    //
}
