<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Holiday;
use App\Services\CalendarService;
use App\Services\DashboardService;
use App\Services\SettingService;
use App\Services\Somiti\SomitiScheduleService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(CalendarService $calendar, SettingService $settings, DashboardService $dashboard, SomitiScheduleService $schedule): View
    {
        $schedule->rollAll(today()); // so today's Somiti dues are counted

        return view('admin.dashboard', [
            'today' => $calendar->todayStatus(),
            'shareValue' => $settings->shareValue(),
            'd' => $dashboard->admin(),
            'upcomingHolidays' => Holiday::query()->whereDate('date', '>=', today())->orderBy('date')->limit(4)->get(),
        ]);
    }
}
