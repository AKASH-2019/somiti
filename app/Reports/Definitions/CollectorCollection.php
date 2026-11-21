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

class CollectorCollection extends Report
{
    public function key(): string { return 'collector-collection'; }

    public function title(): string { return 'কালেক্টরভিত্তিক সংগ্রহ'; }

    public function description(): string { return 'নির্দিষ্ট সময়ে প্রত্যেক কালেক্টরের সমিতি ও কিস্তি সংগ্রহ'; }

    public function period(): string { return 'range'; }

    public function filters(): array { return []; }

    public function build(ReportFilters $f): ReportResult
    {
        $range = [$f->from->copy()->startOfDay(), $f->to->copy()->endOfDay()];
        $sums = DB::table('transactions as t')->join('accounts as a', 'a.id', '=', 't.account_id')
            ->where('t.entry_type', 'cash')->whereBetween('t.collected_at', $range)
            ->selectRaw('t.collector_id, a.type, t.status, SUM(t.amount) as total, COUNT(*) as n')
            ->groupBy('t.collector_id', 'a.type', 't.status')->get()->groupBy('collector_id');

        $rows = User::query()->cashHolders()->whereIn('id', $sums->keys())->orderBy('name')->get()
            ->map(function (User $u) use ($sums) {
                $g = $sums[$u->id];
                $ok = $g->where('status', 'completed');
                $somiti = $this->m($ok->firstWhere('type', 'somiti')?->total ?? 0);
                $kisti = $this->m($ok->firstWhere('type', 'kisti')?->total ?? 0);

                return [
                    'name' => $u->name.($u->isAdmin() ? ' (এডমিন)' : ''),
                    'receipts' => (int) $ok->sum('n'),
                    'somiti' => $somiti,
                    'kisti' => $kisti,
                    'total' => Money::add($somiti, $kisti),
                    'reversed' => $this->m($g->where('status', 'reversed')->sum('total')),
                ];
            })->all();

        $totals = ['name' => 'মোট', 'receipts' => $this->count($rows, 'receipts')];
        foreach (['somiti', 'kisti', 'total', 'reversed'] as $k) {
            $totals[$k] = $this->sum($rows, $k);
        }

        return new ReportResult(
            [
                new Column('name', 'কালেক্টর'), new Column('receipts', 'রসিদ', 'int'),
                new Column('somiti', 'সমিতি সংগ্রহ', 'money'), new Column('kisti', 'কিস্তি সংগ্রহ', 'money'),
                new Column('total', 'মোট সংগ্রহ', 'money'), new Column('reversed', 'বাতিলকৃত', 'money'),
            ],
            $rows, $totals,
            ['মোট সংগ্রহ' => BanglaFormatter::money($totals['total']), 'রসিদ' => BanglaFormatter::digits($totals['receipts']).'টি'],
            'বাতিলকৃত কলামে এই সময়ে নেওয়া কিন্তু পরে বাতিল হওয়া লেনদেন; মোট সংগ্রহে সেগুলো ধরা হয়নি।',
        );
    }
}
