<?php

namespace App\Enums;

/**
 * Who wrote the first draft. AI- and MCP-origin articles always need a human reviewer who is not their author.
 */
enum PostOrigin: string
{
    case Human = 'human';
    case Ai = 'ai';
    case Mcp = 'mcp';

    public function label(): string
    {
        return match ($this) {
            self::Human => __('Human'),
            self::Ai => __('AI draft'),
            self::Mcp => __('MCP draft'),
        };
    }

    /**
     * Machine-written first drafts (AI Content Studio or an MCP client).
     */
    public function isMachine(): bool
    {
        return $this !== self::Human;
    }
}
