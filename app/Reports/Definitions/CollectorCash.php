<?php

namespace App\Reports\Definitions;

use App\Models\User;
use App\Reports\Column;
use App\Reports\Report;
use App\Reports\ReportFilters;
use App\Reports\ReportResult;
use App\Support\BanglaFormatter;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/** Opening balance, every kind of movement, closing balance — per cash holder, from the ledger. */
class CollectorCash extends Report
{
    public function key(): string { return 'collector-cash'; }

    public function title(): string { return 'কালেক্টর ক্যাশ রিপোর্ট'; }

    public function description(): string { return 'সময়ের শুরু ও শেষে হাতে থাকা টাকা এবং মাঝের সব আসা-যাওয়া'; }

    public function period(): string { return 'range'; }

    public function group(): string { return 'ক্যাশ'; }

    public function availableToCollectors(): bool { return true; }

    public function landscape(): bool { return true; }

    protected function rows(ReportFilters $f): array
    {
        $start = $f->from->copy()->startOfDay();
        $end = $f->to->copy()->endOfDay();

        $before = DB::table('collector_cash_ledger')->where('transaction_datetime', '<', $start)
            ->selectRaw('collector_id, SUM(credit) - SUM(debit) as bal')->groupBy('collector_id')->pluck('bal', 'collector_id');
        $moves = DB::table('collector_cash_ledger')->whereBetween('transaction_datetime', [$start, $end])
            ->selectRaw('collector_id, transaction_type, SUM(credit) as c, SUM(debit) as d')
            ->groupBy('collector_id', 'transaction_type')->get()->groupBy('collector_id');

        return User::query()->cashHolders()
            ->when($f->collectorId, fn ($q) => $q->whereKey($f->collectorId))
            ->orderBy('role')->orderBy('name')->get()
            ->filter(fn (User $u) => $u->is_active || isset($before[$u->id]) || isset($moves[$u->id]))
            ->map(function (User $u) use ($before, $moves) {
                $g = $moves[$u->id] ?? collect();
                $v = fn (string $type, string $side) => $this->m($g->firstWhere('transaction_type', $type)?->{$side} ?? 0);
                $opening = $this->m($before[$u->id] ?? 0);
                $collection = Money::sub(Money::add($v('collection', 'c'), $v('opening', 'c')), $v('collection_reversal', 'd'));
                $adjust = Money::sub($v('adjustment', 'c'), $v('adjustment', 'd'));
                $in = $v('handover_in', 'c');
                $out = $v('handover_out', 'd');

                return [
                    'name' => $u->name.($u->isAdmin() ? ' (এডমিন)' : ''),
                    'opening' => $opening,
                    'collection' => $collection,
                    'handover_in' => $in,
                    'handover_out' => $out,
                    'adjustment' => $adjust,
                    'closing' => Money::sub(Money::add($opening, $collection, $in, $adjust), $out),
                ];
            })->values()->all();
    }

    public function build(ReportFilters $f): ReportResult
    {
        $rows = $this->rows($f);
        $totals = ['name' => 'মোট'];
        foreach (['opening', 'collection', 'handover_in', 'handover_out', 'adjustment', 'closing'] as $k) {
            $totals[$k] = $this->sum($rows, $k);
        }

        return new ReportResult(
            $this->columns(),
            $rows, $totals,
            ['শুরুতে হাতে' => BanglaFormatter::money($totals['opening']), 'সংগ্রহ' => BanglaFormatter::money($totals['collection']), 'শেষে হাতে' => BanglaFormatter::money($totals['closing'])],
            'সংগ্রহ = নগদ সংগ্রহ (প্রারম্ভিক ক্যাশসহ) − বাতিলকৃত সংগ্রহ। সবার মোটে হস্তান্তর গ্রহণ ও প্রদান সমান হয়।',
        );
    }

    protected function columns(): array
    {
        return [
            new Column('name', 'নাম'), new Column('opening', 'শুরুর ব্যালেন্স', 'money'), new Column('collection', 'সংগ্রহ', 'money'),
            new Column('handover_in', 'হস্তান্তর গ্রহণ', 'money'), new Column('handover_out', 'হস্তান্তর প্রদান', 'money'),
            new Column('adjustment', 'সমন্বয়', 'money'), new Column('closing', 'শেষ ব্যালেন্স', 'money'),
        ];
    }
}
