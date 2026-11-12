<?php

namespace App\Enums;

/**
 * How an assistant conversation runs: the AI interview, or the deterministic guided questionnaire (fallback).
 */
enum ChatMode: string
{
    case Ai = 'ai';
    case Guided = 'guided';

    public function label(): string
    {
        return match ($this) {
            self::Ai => __('AI conversation'),
            self::Guided => __('Guided questionnaire'),
        };
    }
}
