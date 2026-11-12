<?php

namespace App\Http\Controllers\Admin;

use App\Enums\KillSwitchKey;
use App\Http\Controllers\Controller;
use App\Services\KillSwitches;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class KillSwitchController extends Controller
{
    public function index(KillSwitches $killSwitches): View
    {
        return view('admin.emergency', [
            'switches' => collect(KillSwitchKey::cases())->map(fn (KillSwitchKey $key): array => [
                'key' => $key,
                'active' => $killSwitches->isActive($key),
            ]),
        ]);
    }

    public function update(Request $request, string $key, KillSwitches $killSwitches): RedirectResponse
    {
        $switch = KillSwitchKey::tryFrom($key);
        abort_if($switch === null, 404);

        $validated = $request->validate([
            'active' => ['required', 'boolean'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $killSwitches->set($switch, (bool) $validated['active'], $request->user(), $validated['reason'] ?? null);

        return back()->with('toast', __('Emergency control updated.'));
    }
}
