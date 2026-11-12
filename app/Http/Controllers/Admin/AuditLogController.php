<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ActorType;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function __invoke(Request $request): View
    {
        $actorType = ActorType::tryFrom($request->string('actor')->toString());

        return view('admin.audit-logs', [
            'logs' => AuditLog::query()
                ->when($actorType !== null, fn ($query) => $query->where('actor_type', $actorType))
                ->orderByDesc('id')
                ->paginate(25)
                ->withQueryString(),
            'actorType' => $actorType,
        ]);
    }
}
