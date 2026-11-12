<?php

namespace App\Http\Controllers;

use App\Models\Testimonial;
use Illuminate\View\View;

class TestimonialController extends Controller
{
    public function __invoke(): View
    {
        return view('testimonials.index', [
            'testimonials' => Testimonial::query()
                ->publiclyVisible()
                ->translatedIn()
                ->with(['translations', 'avatar.variants', 'project' => fn ($query) => $query->publiclyListed()->with('translations')])
                ->orderByDesc('is_featured')
                ->ordered()
                ->get(),
        ]);
    }
}
