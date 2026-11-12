<?php

namespace App\Http\Controllers;

use App\Enums\TechnologyProficiency;
use App\Models\TechnologyCategory;
use Illuminate\View\View;

class TechnologyController extends Controller
{
    public function __invoke(): View
    {
        $categories = TechnologyCategory::query()
            ->whereHas('technologies', fn ($query) => $query->where('is_visible', true))
            ->with([
                'translations',
                'technologies' => fn ($query) => $query->where('is_visible', true)->withProficiency()->with('translations')->ordered(),
            ])
            ->ordered()
            ->get();

        return view('technologies.index', [
            'categories' => $categories,
            'levels' => [TechnologyProficiency::VerifiedInProjects, TechnologyProficiency::Expertise, TechnologyProficiency::Supported],
        ]);
    }
}
