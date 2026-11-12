<?php

namespace App\Enums;

enum ChangeAction: string
{
    case Update = 'update';
    case Publish = 'publish';
}
