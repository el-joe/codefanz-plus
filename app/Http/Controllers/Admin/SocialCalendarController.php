<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SocialPlatform;
use App\Enums\SocialPostStatus;
use App\Http\Controllers\Controller;
use App\Models\SocialPost;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * B07 Content calendar: server-rendered month and week grids in the staff member's time zone. Posts sit at their
 * schedule, their publication time or — for drafts and posts in review — their planned time ("pending approval").
 * Drag-and-drop (and the keyboard form) call the reschedule endpoint, which re-validates approval and time.
 */
class SocialCalendarController extends Controller
{
    /**
     * Weeks start on Saturday, as in the signed-off design (Egypt / Gulf working week).
     */
    public const WEEK_START = CarbonInterface::SATURDAY;

    public function __invoke(Request $request): View
    {
        $filters = $request->validate([
            'view' => ['nullable', 'in:month,week'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['nullable', 'string', 'in:'.implode(',', array_column(SocialPostStatus::cases(), 'value'))],
            'platform' => ['nullable', 'string', 'in:'.implode(',', array_column(SocialPlatform::cases(), 'value'))],
            'campaign' => ['nullable', 'string', 'max:80'],
        ]);

        $timezone = $request->user()->timezone ?: config('app.timezone');
        $view = $filters['view'] ?? 'month';
        $anchor = isset($filters['date']) ? CarbonImmutable::createFromFormat('Y-m-d', $filters['date'], $timezone)->startOfDay() : CarbonImmutable::now($timezone)->startOfDay();

        if ($view === 'week') {
            $start = $anchor->startOfWeek(self::WEEK_START);
            $end = $start->addDays(6)->endOfDay();
            $previous = $start->subWeek();
            $next = $start->addWeek();
        } else {
            $start = $anchor->startOfMonth()->startOfWeek(self::WEEK_START);
            $end = $anchor->endOfMonth()->endOfWeek(self::WEEK_START === CarbonInterface::SATURDAY ? CarbonInterface::FRIDAY : CarbonInterface::SUNDAY);
            $previous = $anchor->startOfMonth()->subMonth();
            $next = $anchor->startOfMonth()->addMonth();
        }

        $rangeStart = $start->utc();
        $rangeEnd = $end->utc();

        $posts = SocialPost::query()
            ->with('variants')
            ->where('status', '!=', SocialPostStatus::Cancelled)
            ->where(fn ($query) => $query
                ->whereBetween('scheduled_at', [$rangeStart, $rangeEnd])
                ->orWhere(fn ($published) => $published->whereNull('scheduled_at')->whereBetween('published_at', [$rangeStart, $rangeEnd]))
                ->orWhere(fn ($planned) => $planned->whereNull('scheduled_at')->whereNull('published_at')->whereBetween('planned_at', [$rangeStart, $rangeEnd])))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['platform'] ?? null, fn ($query, $platform) => $query->whereHas('variants', fn ($variants) => $variants->where('platform', $platform)))
            ->when($filters['campaign'] ?? null, fn ($query, $campaign) => $query->where('campaign', $campaign))
            ->limit(500)
            ->get()
            ->sortBy(fn (SocialPost $post) => $post->calendarAt()?->getTimestamp())
            ->values();

        /** @var Collection<string, Collection<int, SocialPost>> $byDay */
        $byDay = $posts->groupBy(fn (SocialPost $post): string => $post->calendarAt()->timezone($timezone)->format('Y-m-d'));

        $days = [];

        for ($day = $start; $day->lte($end); $day = $day->addDay()) {
            $days[] = $day;
        }

        return view('admin.social.calendar', [
            'view' => $view,
            'anchor' => $anchor,
            'days' => $days,
            'byDay' => $byDay,
            'timezone' => $timezone,
            'previous' => $previous,
            'next' => $next,
            'filters' => $filters,
            'campaigns' => SocialPost::query()->whereNotNull('campaign')->distinct()->orderBy('campaign')->limit(100)->pluck('campaign')->all(),
            'today' => CarbonImmutable::now($timezone)->format('Y-m-d'),
        ]);
    }
}
