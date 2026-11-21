<?php

namespace App\Reports\Definitions;

use App\Reports\Column;
use App\Reports\Report;
use App\Reports\ReportFilters;
use App\Reports\ReportResult;
use App\Support\BanglaFormatter;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/** Day-by-day Somiti / Kisti totals over a period. */
abstract class PeriodCollection extends Report
{
    public function availableToCollectors(): bool
    {
        return true;
    }

    public function build(ReportFilters $f): ReportResult
    {
        $sums = DB::table('transactions as t')->join('accounts as a', 'a.id', '=', 't.account_id')
            ->where('t.status', 'completed')->where('t.entry_type', 'cash')
            ->whereBetween('t.collected_at', [$f->from->copy()->startOfDay(), $f->to->copy()->endOfDay()])
            ->when($f->collectorId, fn ($q) => $q->where('t.collector_id', $f->collectorId))
            ->selectRaw('DATE(t.collected_at) as d, a.type as type, SUM(t.amount) as total, COUNT(*) as n')
            ->groupByRaw('DATE(t.collected_at), a.type')->get()
            ->groupBy('d');

        $rows = [];
        for ($d = $f->from->copy(); $d->lte($f->to); $d->addDay()) {
            $day = $sums[$d->toDateString()] ?? collect();
            $somiti = $this->m($day->firstWhere('type', 'somiti')?->total ?? 0);
            $kisti = $this->m($day->firstWhere('type', 'kisti')?->total ?? 0);
            $rows[] = [
                'date' => $d->toDateString(),
                'day' => BanglaFormatter::DAYS[$d->dayOfWeek],
                'somiti' => $somiti,
                'kisti' => $kisti,
                'total' => Money::add($somiti, $kisti),
                'receipts' => (int) $day->sum('n'),
            ];
        }

        $totals = [
            'date' => 'মোট',
            'somiti' => $this->sum($rows, 'somiti'),
            'kisti' => $this->sum($rows, 'kisti'),
            'total' => $this->sum($rows, 'total'),
            'receipts' => $this->count($rows, 'receipts'),
        ];

        return new ReportResult(
            [
                new Column('date', 'তারিখ', 'date'),
                new Column('day', 'বার'),
                new Column('somiti', 'সমিতি সংগ্রহ', 'money'),
                new Column('kisti', 'কিস্তি সংগ্রহ', 'money'),
                new Column('total', 'মোট', 'money'),
                new Column('receipts', 'রসিদ', 'int'),
            ],
            $rows,
            $totals,
            [
                'সমিতি' => BanglaFormatter::money($totals['somiti']),
                'কিস্তি' => BanglaFormatter::money($totals['kisti']),
                'মোট সংগ্রহ' => BanglaFormatter::money($totals['total']),
                'রসিদ' => BanglaFormatter::digits($totals['receipts']).'টি',
            ],
            'শুধু নগদ সংগ্রহ; বাতিলকৃত লেনদেন বাদে।',
        );
    }
}
