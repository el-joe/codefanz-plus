<?php

namespace App\Enums;

enum ChangeOrigin: string
{
    case Human = 'human';
    case Ai = 'ai';
    case Mcp = 'mcp';

    public function actorType(): ActorType
    {
        return match ($this) {
            self::Human => ActorType::User,
            self::Ai => ActorType::AiAgent,
            self::Mcp => ActorType::McpClient,
        };
    }

    /**
     * The kill switch that blocks changes from this origin, if any.
     */
    public function killSwitch(): ?KillSwitchKey
    {
        return match ($this) {
            self::Human => null,
            self::Ai => KillSwitchKey::AiAutomation,
            self::Mcp => KillSwitchKey::McpWrites,
        };
    }
}
