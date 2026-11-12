<?php

namespace App\Services;

use App\Enums\ActorType;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditLogger
{
    public function __construct(private Request $request) {}

    /**
     * Record an append-only audit entry.
     *
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function record(
        string $operation,
        ?Model $auditable = null,
        ?User $user = null,
        ActorType $actorType = ActorType::User,
        ?string $actorLabel = null,
        ?array $before = null,
        ?array $after = null,
        string $result = 'success',
        ?int $changeRequestId = null,
    ): AuditLog {
        return AuditLog::create([
            'actor_type' => $actorType,
            'user_id' => $user?->getKey(),
            'actor_label' => $actorLabel ?? $user?->name,
            'operation' => $operation,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'change_request_id' => $changeRequestId,
            'before' => $before,
            'after' => $after,
            'result' => $result,
            'ip_address' => $this->request->ip(),
            'user_agent' => mb_substr((string) $this->request->userAgent(), 0, 255) ?: null,
        ]);
    }
}
