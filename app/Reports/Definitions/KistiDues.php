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

class KistiDues extends Report
{
    public function key(): string { return 'kisti-dues'; }

    public function title(): string { return 'কিস্তির বকেয়া রিপোর্ট'; }

    public function description(): string { return 'যেসব ঋণে আজ পর্যন্ত কিস্তি বকেয়া আছে, সবচেয়ে বেশি বকেয়া আগে'; }

    public function period(): string { return 'none'; }

    public function group(): string { return 'বকেয়া ও অগ্রিম'; }

    public function availableToCollectors(): bool { return true; }

    public function landscape(): bool { return true; }

    public function build(ReportFilters $f): ReportResult
    {
        $today = today()->toDateString();
        $accounts = Account::query()->kisti()->where('status', 'active')
            ->when($f->collectorId, fn ($q) => $q->where('assigned_collector_id', $f->collectorId))
            ->with(['member:id,name,member_code,mobile', 'kistiDetail', 'collector:id,name'])->get()->keyBy('id');

        $due = DB::table('installments')->whereIn('account_id', $accounts->keys())
            ->whereDate('due_date', '<=', $today)->whereColumn('amount_paid', '<', 'amount_due')
            ->selectRaw('account_id, COUNT(*) as n, SUM(amount_due - amount_paid) as due, MIN(due_date) as oldest')
            ->groupBy('account_id')->get()->keyBy('account_id');
        $paid = DB::table('installments')->whereIn('account_id', $due->keys())
            ->selectRaw('account_id, SUM(amount_paid) as paid')->groupBy('account_id')->pluck('paid', 'account_id');
        $last = DB::table('transactions')->whereIn('account_id', $due->keys())->where('status', 'completed')
            ->selectRaw('account_id, MAX(collected_at) as last')->groupBy('account_id')->pluck('last', 'account_id');

        $rows = $due->map(function ($d) use ($accounts, $paid, $last) {
            $a = $accounts[$d->account_id];
            $k = $a->kistiDetail;

            return [
                'account' => $a->account_no,
                'member' => $a->member->name.' ('.$a->member->member_code.')',
                'mobile' => BanglaFormatter::digits($a->member->mobile),
                'collector' => $a->collector?->name,
                'frequency' => $k->frequency->label(),
                'installment' => (string) $k->installment_amount,
                'count' => (int) $d->n,
                'due' => $this->m($d->due),
                'oldest' => $d->oldest,
                'last' => $last[$d->account_id] ?? null,
                'left' => Money::sub((string) $k->total_payable, $this->m($paid[$d->account_id] ?? 0)),
            ];
        })->sortByDesc(fn ($r) => (float) $r['due'])->values()->all();

        $totals = ['account' => 'মোট', 'count' => $this->count($rows, 'count'), 'due' => $this->sum($rows, 'due'), 'left' => $this->sum($rows, 'left')];

        return new ReportResult(
            [
                new Column('account', 'অ্যাকাউন্ট'), new Column('member', 'সদস্য'), new Column('mobile', 'মোবাইল'),
                new Column('collector', 'কালেক্টর'), new Column('frequency', 'ধরন'), new Column('installment', 'কিস্তি', 'money'),
                new Column('count', 'বকেয়া কিস্তি', 'int'), new Column('due', 'বকেয়া টাকা', 'money'),
                new Column('oldest', 'প্রথম বকেয়া', 'date'), new Column('last', 'শেষ জমা', 'date'), new Column('left', 'অবশিষ্ট ঋণ', 'money'),
            ],
            $rows, $totals,
            [
                'বকেয়াদার ঋণ' => BanglaFormatter::digits(count($rows)).'টি',
                'মোট বকেয়া' => BanglaFormatter::money($totals['due']),
                'বকেয়া কিস্তি' => BanglaFormatter::digits($totals['count']).'টি',
            ],
        );
    }
}
