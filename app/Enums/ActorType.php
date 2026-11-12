<?php

namespace App\Enums;

enum ActorType: string
{
    case User = 'user';
    case AiAgent = 'ai_agent';
    case McpClient = 'mcp_client';
    case System = 'system';
}
