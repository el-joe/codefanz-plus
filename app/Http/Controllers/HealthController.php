<?php

namespace App\Http\Controllers;

use App\Services\Operations\HealthCheck;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * GET /health for uptime monitors and the deploy script: 200 when every check passes, 503 otherwise.
 * Anyone sees only the overall status; per-check details need an allow-listed IP or the `X-Health-Token` header.
 */
class HealthController extends Controller
{
    public function __invoke(Request $request, HealthCheck $health): JsonResponse
    {
        $result = $health->run();

        $body = ['status' => $result['ok'] ? 'ok' : 'fail'];

        if ($this->maySeeDetails($request)) {
            $body += ['checked_at' => now()->toIso8601String(), 'checks' => $result['checks']];
        }

        return response()->json($body, $result['ok'] ? 200 : 503)
            ->header('Cache-Control', 'no-store, private');
    }

    private function maySeeDetails(Request $request): bool
    {
        $token = config('codefanz.operations.health.token');
        $given = $request->header('X-Health-Token');

        if (is_string($token) && $token !== '' && is_string($given) && hash_equals($token, $given)) {
            return true;
        }

        $allowed = (array) config('codefanz.operations.health.allowed_ips', []);

        return $allowed !== [] && $request->ip() !== null && IpUtils::checkIp($request->ip(), $allowed);
    }
}
