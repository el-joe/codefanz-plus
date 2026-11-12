<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Language;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * The signed-in staff member's public author profile (job title and bio per language) shown on blog articles.
 */
class AuthorProfileController extends Controller
{
    public function __invoke(Request $request, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize(Permission::ContentManage->value);

        $rules = [];

        foreach (Language::active()->keys() as $code) {
            $rules["author_title.{$code}"] = ['nullable', 'string', 'max:100'];
            $rules["author_bio.{$code}"] = ['nullable', 'string', 'max:600'];
        }

        $validated = $request->validate($rules);
        $user = $request->user();
        $before = $user->only(['author_title', 'author_bio']);

        $user->forceFill([
            'author_title' => array_filter($validated['author_title'] ?? [], 'filled') ?: null,
            'author_bio' => array_filter($validated['author_bio'] ?? [], 'filled') ?: null,
        ])->save();

        $audit->record(operation: 'profile.author_updated', auditable: $user, user: $user, before: $before, after: $user->only(['author_title', 'author_bio']));

        return back()->with('toast', __('Author profile saved.'));
    }
}
