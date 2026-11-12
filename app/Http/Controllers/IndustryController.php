<?php

namespace App\Http\Controllers;

use App\Models\Industry;
use Illuminate\View\View;

class IndustryController extends Controller
{
    public function __invoke(): View
    {
        return view('industries.index', [
            'industries' => Industry::query()
                ->published()
                ->translatedIn()
                ->with([
                    'translations',
                    'portfolioProjects' => fn ($query) => $query->publiclyListed()->translatedIn()->with('translations')->ordered()->limit(3),
                ])
                ->ordered()
                ->get(),
        ]);
    }
}
