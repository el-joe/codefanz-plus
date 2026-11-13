<?php

namespace App\Http\Controllers\Api\V1\Mcp;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * whoami: the calling client, its scopes and limits (never the token).
 */
class ClientController extends McpController
{
    public function __invoke(Request $request): JsonResponse
    {
        $client = $this->client($request);

        return new JsonResponse(['data' => [
            'name' => $client->name,
            'owner' => $client->owner?->name,
            'scopes' => $client->scopes,
            'rate_limit_per_minute' => $client->rate_limit_per_minute,
            'lead_personal_data' => $client->allow_lead_pii,
            'expires_at' => $client->expires_at?->toIso8601String(),
            'api_version' => 'v1',
        ]]);
    }
}
