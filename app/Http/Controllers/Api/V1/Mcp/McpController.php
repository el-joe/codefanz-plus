<?php

namespace App\Http\Controllers\Api\V1\Mcp;

use App\Enums\McpScope;
use App\Http\Controllers\Controller;
use App\Http\Middleware\AuthorizeMcpCall;
use App\Models\ChangeRequest;
use App\Models\McpClient;
use App\Models\User;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shared helpers for the MCP API: the calling client and its owner, page sizes and the cursor-paginated envelope
 * {"data": [...], "meta": {"per_page", "next_cursor", "prev_cursor"}}.
 */
abstract class McpController extends Controller
{
    protected function client(Request $request): McpClient
    {
        /** @var McpClient */
        return $request->user();
    }

    protected function owner(Request $request): User
    {
        /** @var User */
        return $this->client($request)->owner;
    }

    protected function can(Request $request, McpScope $scope): bool
    {
        return $this->client($request)->hasScope($scope);
    }

    protected function perPage(Request $request): int
    {
        $default = (int) config('codefanz.mcp.default_per_page');

        return max(1, min((int) config('codefanz.mcp.max_per_page'), (int) $request->query('per_page', (string) $default) ?: $default));
    }

    /**
     * @param  class-string<JsonResource>  $resource
     */
    protected function paginated(CursorPaginator $paginator, string $resource, Request $request): JsonResponse
    {
        return new JsonResponse([
            'data' => $resource::collection($paginator->getCollection())->resolve($request),
            'meta' => [
                'per_page' => $paginator->perPage(),
                'next_cursor' => $paginator->nextCursor()?->encode(),
                'prev_cursor' => $paginator->previousCursor()?->encode(),
            ],
        ]);
    }

    /**
     * Links the change request to this call's mcp_tool_calls row.
     */
    protected function remember(Request $request, ChangeRequest $changeRequest): void
    {
        // Form requests are copies of the routed request: set it on the original, which the middleware logs.
        $request->attributes->set(AuthorizeMcpCall::CHANGE_REQUEST_ATTRIBUTE, $changeRequest->getKey());
        app('request')->attributes->set(AuthorizeMcpCall::CHANGE_REQUEST_ATTRIBUTE, $changeRequest->getKey());
    }
}
