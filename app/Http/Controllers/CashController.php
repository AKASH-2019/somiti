<?php

namespace App\Http\Controllers;

use App\Http\Requests\Cash\CashAdjustmentRequest;
use App\Models\User;
use App\Services\Cash\CashAdjustmentService;
use App\Services\Cash\CashStatementService;
use App\Services\Cash\HandoverService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class CashController extends Controller
{
    public function __construct(private CashStatementService $statements, private HandoverService $handovers) {}

    /** Admin: every cash holder's position. */
    public function index(): View
    {
        $rows = $this->statements->overview();

        return view('cash.index', [
            'rows' => $rows,
            'total' => \App\Support\Money::add('0', ...$rows->pluck('balance')->all()),
        ]);
    }

    /** Admin: one holder's statement. */
    public function show(Request $request, User $holder): View
    {
        abort_unless($holder->isAdmin() || $holder->isCollector(), 404);

        return $this->statementView($request, $holder, true);
    }

    /** Collector: own statement. */
    public function mine(Request $request): View
    {
        return $this->statementView($request, $request->user(), false);
    }

    public function adjust(CashAdjustmentRequest $request, User $holder, CashAdjustmentService $service): RedirectResponse
    {
        try {
            $service->adjust($holder, $request->validated('direction'), (string) $request->validated('amount'),
                $request->validated('reason'), $request->user());
        } catch (DomainException $e) {
            return back()->withErrors(['amount' => $e->getMessage()], 'adjust');
        }

        return back()->with('success', "{$holder->name}-এর ক্যাশ সমন্বয় করা হয়েছে।");
    }

    private function statementView(Request $request, User $holder, bool $adminView): View
    {
        $from = $this->date($request->query('from')) ?? today()->startOfMonth();
        $to = $this->date($request->query('to')) ?? today();
        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }

        return view('cash.statement', [
            'holder' => $holder,
            'from' => $from,
            'to' => $to,
            'st' => $this->statements->statement($holder, $from, $to),
            'available' => $this->handovers->available($holder),
            'adminView' => $adminView,
        ]);
    }

    private function date(?string $v): ?Carbon
    {
        return $v && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? Carbon::parse($v)->startOfDay() : null;
    }
}
