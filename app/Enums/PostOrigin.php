<?php

namespace App\Enums;

/**
 * Who wrote the first draft. AI-origin articles always need a human reviewer who is not their author.
 */
enum PostOrigin: string
{
    case Human = 'human';
    case Ai = 'ai';

    public function label(): string
    {
        return match ($this) {
            self::Human => __('Human'),
            self::Ai => __('AI draft'),
        };
    }
}
