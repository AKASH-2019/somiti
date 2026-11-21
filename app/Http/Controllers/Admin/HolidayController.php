<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreHolidayRequest;
use App\Models\Holiday;
use App\Services\AuditService;
use App\Services\Kisti\KistiScheduleService;
use App\Services\Somiti\SomitiScheduleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HolidayController extends Controller
{
    public function index(Request $request): View
    {
        $year = (int) $request->integer('year', (int) now()->format('Y'));

        return view('admin.holidays.index', [
            'year' => $year,
            'years' => range((int) now()->format('Y') - 2, (int) now()->format('Y') + 1),
            'holidays' => Holiday::query()->whereYear('date', $year)->orderBy('date')->get(),
        ]);
    }

    public function store(StoreHolidayRequest $request, AuditService $audit, SomitiScheduleService $schedule, KistiScheduleService $kisti): RedirectResponse
    {
        $holiday = Holiday::query()->create($request->validated() + ['created_by' => $request->user()->id]);
        $removed = $schedule->onHolidayAdded($holiday->date);
        $moved = $kisti->reflowAll($holiday->date);
        $audit->log('holiday.created', $holiday, [], $holiday->only('date', 'title') + [
            'unpaid_somiti_days_removed' => $removed, 'kisti_installments_moved' => $moved,
        ]);

        return redirect()->route('admin.holidays.index', ['year' => $holiday->date->year])
            ->with('success', 'ছুটির দিন যোগ করা হয়েছে।');
    }

    /**
     * Past holidays are locked: schedules for those dates may already be settled.
     * (From Phase 4, removing a future holiday will also re-flow unpaid Kisti dates.)
     */
    public function destroy(Holiday $holiday, AuditService $audit, SomitiScheduleService $schedule, KistiScheduleService $kisti): RedirectResponse
    {
        if ($holiday->date->lt(today())) {
            return back()->with('error', 'অতীতের ছুটির দিন মুছে ফেলা যাবে না।');
        }

        $audit->log('holiday.deleted', $holiday, $holiday->only('date', 'title'));
        $date = $holiday->date->copy();
        $holiday->delete();
        $schedule->onHolidayRemoved($date);
        $kisti->reflowAll($date);

        return back()->with('success', 'ছুটির দিন মুছে ফেলা হয়েছে।');
    }
}
