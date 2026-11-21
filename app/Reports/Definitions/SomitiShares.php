<?php

namespace App\Reports\Definitions;

use App\Models\Account;
use App\Reports\Column;
use App\Reports\Report;
use App\Reports\ReportFilters;
use App\Reports\ReportResult;
use App\Support\BanglaFormatter;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

class SomitiShares extends Report
{
    public function key(): string { return 'somiti-shares'; }

    public function title(): string { return 'সমিতির শেয়ারভিত্তিক হিসাব'; }

    public function description(): string { return 'চালু সমিতি অ্যাকাউন্টের শেয়ার, দৈনিক জমা, সঞ্চয়, বকেয়া ও অগ্রিম'; }

    public function period(): string { return 'none'; }

    public function group(): string { return 'হিসাব'; }

    public function landscape(): bool { return true; }

    public function build(ReportFilters $f): ReportResult
    {
        $today = today()->toDateString();
        $accounts = Account::query()->somiti()->where('status', 'active')
            ->when($f->collectorId, fn ($q) => $q->where('assigned_collector_id', $f->collectorId))
            ->with(['member:id,name,member_code', 'somitiDetail'])->orderBy('account_no')->get();

        $agg = DB::table('installments')->whereIn('account_id', $accounts->pluck('id'))
            ->selectRaw('account_id, SUM(amount_paid) as paid,
                SUM(CASE WHEN due_date <= ? THEN amount_due - amount_paid ELSE 0 END) as due,
                SUM(CASE WHEN due_date > ? THEN amount_paid ELSE 0 END) as advance', [$today, $today])
            ->groupBy('account_id')->get()->keyBy('account_id');

        $rows = $accounts->map(function (Account $a) use ($agg) {
            $d = $a->somitiDetail;
            $x = $agg[$a->id] ?? null;

            return [
                'account' => $a->account_no,
                'member' => $a->member->name.' ('.$a->member->member_code.')',
                'shares' => $d->number_of_shares,
                'daily' => (string) $d->daily_deposit_amount,
                'start' => $d->start_date,
                'opening' => (string) $d->opening_balance,
                'collected' => $this->m($x?->paid ?? 0),
                'saved' => Money::add((string) $d->opening_balance, $this->m($x?->paid ?? 0)),
                'due' => $this->m(max(0, (float) ($x?->due ?? 0))),
                'advance' => $this->m($x?->advance ?? 0),
            ];
        })->all();

        $totals = ['account' => 'মোট', 'shares' => $this->count($rows, 'shares')];
        foreach (['daily', 'opening', 'collected', 'saved', 'due', 'advance'] as $k) {
            $totals[$k] = $this->sum($rows, $k);
        }

        $summary = [
            'চালু অ্যাকাউন্ট' => BanglaFormatter::digits(count($rows)).'টি',
            'মোট শেয়ার' => BanglaFormatter::digits($totals['shares']),
            'দৈনিক প্রত্যাশিত জমা' => BanglaFormatter::money($totals['daily']),
            'মোট সঞ্চয়' => BanglaFormatter::money($totals['saved']),
        ];
        foreach (collect($rows)->groupBy('shares')->sortKeys() as $shares => $group) {
            $summary[BanglaFormatter::digits($shares).' শেয়ার'] = BanglaFormatter::digits($group->count()).'টি অ্যাকাউন্ট';
        }

        return new ReportResult(
            [
                new Column('account', 'অ্যাকাউন্ট'), new Column('member', 'সদস্য'), new Column('shares', 'শেয়ার', 'int'),
                new Column('daily', 'দৈনিক জমা', 'money'), new Column('start', 'শুরুর তারিখ', 'date'),
                new Column('opening', 'প্রারম্ভিক সঞ্চয়', 'money'), new Column('collected', 'সংগ্রহ', 'money'),
                new Column('saved', 'মোট সঞ্চয়', 'money'), new Column('due', 'বকেয়া', 'money'), new Column('advance', 'অগ্রিম', 'money'),
            ],
            $rows, $totals, $summary,
        );
    }
}
