<?php

namespace App\Reports\Definitions;

use App\Models\CashHandover;
use App\Reports\ReportFilters;
use App\Reports\ReportResult;
use App\Support\BanglaFormatter;
use Illuminate\Support\Facades\DB;

/** End-of-day cash position: the cash report for a single day, with the organisation's total. */
class DailyCashClosing extends CollectorCash
{
    public function key(): string { return 'daily-cash-closing'; }

    public function title(): string { return 'দৈনিক ক্যাশ ক্লোজিং রিপোর্ট'; }

    public function description(): string { return 'দিনের শেষে কার হাতে কত টাকা, দিনের সংগ্রহ ও হস্তান্তরসহ'; }

    public function period(): string { return 'date'; }

    public function build(ReportFilters $f): ReportResult
    {
        $rows = $this->rows($f);
        $totals = ['name' => 'মোট'];
        foreach (['opening', 'collection', 'handover_in', 'handover_out', 'adjustment', 'closing'] as $k) {
            $totals[$k] = $this->sum($rows, $k);
        }

        $receipts = DB::table('transactions')->where('status', 'completed')->where('entry_type', 'cash')
            ->whereBetween('collected_at', [$f->from->copy()->startOfDay(), $f->from->copy()->endOfDay()])
            ->when($f->collectorId, fn ($q) => $q->where('collector_id', $f->collectorId))->count();
        $pending = CashHandover::query()->where('status', 'pending')
            ->whereDate('handover_datetime', '<=', $f->from->toDateString())
            ->when($f->collectorId, fn ($q) => $q->where(fn ($w) => $w->where('sender_collector_id', $f->collectorId)->orWhere('receiver_collector_id', $f->collectorId)));

        return new ReportResult(
            $this->columns(),
            $rows, $totals,
            [
                'দিনের শুরুতে মোট ক্যাশ' => BanglaFormatter::money($totals['opening']),
                'আজকের সংগ্রহ' => BanglaFormatter::money($totals['collection']),
                'রসিদ' => BanglaFormatter::digits($receipts).'টি',
                'দিনের শেষে মোট ক্যাশ' => BanglaFormatter::money($totals['closing']),
                'অপেক্ষমাণ হস্তান্তর' => BanglaFormatter::digits((clone $pending)->count()).'টি, '.BanglaFormatter::money((string) $pending->sum('amount')),
            ],
            'অপেক্ষমাণ হস্তান্তর প্রেরকের ক্যাশেই ধরা আছে।',
        );
    }
}
