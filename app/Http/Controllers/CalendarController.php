<?php

namespace App\Http\Controllers;

use App\Http\Requests\CalendarMonthRequest;
use App\Services\CalendarEvents;
use App\Services\ClassAccessService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\View\View;

class CalendarController extends Controller
{
    public function index(CalendarMonthRequest $request, ClassAccessService $access, CalendarEvents $calendar): View
    {
        $user = $request->user();
        abort_unless($access->ready($user), 403);

        $timezone = config('app.timezone');
        $month = CarbonImmutable::parse(($request->validated('month') ?? now($timezone)->format('Y-m')).'-01', $timezone)->startOfMonth();
        $calendarData = $calendar->for($user, $month);
        $eventsByDay = $calendarData['events']->groupBy(fn (array $event): string => $event['at']->toDateString());
        $days = collect();
        $gridEnd = $month->endOfMonth()->endOfWeek(CarbonInterface::SUNDAY);
        for ($day = $month->startOfWeek(CarbonInterface::MONDAY); $day->lte($gridEnd); $day = $day->addDay()) {
            $days->push($day);
        }

        return view('calendar.index', [
            'month' => $month,
            'weeks' => $days->chunk(7),
            'eventsByDay' => $eventsByDay,
            'limited' => $calendarData['limited'],
            'timezone' => $timezone,
        ]);
    }
}
