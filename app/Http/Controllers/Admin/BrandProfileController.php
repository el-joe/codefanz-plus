<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Ai\SaveBrandProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveBrandProfileRequest;
use App\Models\BrandProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Brand voice (ai.manage): tone, audience, do / don't rules, banned claims, glossary and approved link domains.
 */
class BrandProfileController extends Controller
{
    public function edit(): View
    {
        return view('admin.ai-studio.brand-voice', ['profile' => BrandProfile::current()]);
    }

    public function update(SaveBrandProfileRequest $request, SaveBrandProfile $save): RedirectResponse
    {
        $save->handle(BrandProfile::current(), $request->validated(), $request->user());

        return redirect()->route('admin.brand-voice.edit')->with('toast', __('Brand voice saved.'));
    }
}
