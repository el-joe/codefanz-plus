<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Radar\RadarAnalytics;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RadarAnalyticsController extends Controller
{
    public function __invoke(Request $request, RadarAnalytics $analytics): View
    {
        $days = (int) ($request->validate(['days' => ['nullable', 'integer', Rule::in([7, 30, 90])]])['days'] ?? 30);

        return view('admin.radar.analytics', ['summary' => $analytics->summary($days), 'days' => $days]);
    }
}
