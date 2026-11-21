<?php

namespace App\Http\Controllers\Collector;

use App\Http\Controllers\Controller;
use App\Services\CalendarService;
use App\Services\DashboardService;
use App\Services\Somiti\SomitiScheduleService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, CalendarService $calendar, DashboardService $dashboard, SomitiScheduleService $schedule): View
    {
        $schedule->rollAll(today());

        return view('collector.dashboard', [
            'today' => $calendar->todayStatus(),
            'd' => $dashboard->collector($request->user()),
        ]);
    }
}
