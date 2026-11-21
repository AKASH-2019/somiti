<?php

namespace App\Reports\Definitions;

use App\Models\Account;
use App\Reports\Column;
use App\Reports\Report;
use App\Reports\ReportFilters;
use App\Reports\ReportResult;
use App\Support\BanglaFormatter;
use Illuminate\Support\Facades\DB;

class AdvanceDeposits extends Report
{
    public function key(): string { return 'advance'; }

    public function title(): string { return 'অগ্রিম জমার রিপোর্ট'; }

    public function description(): string { return 'ভবিষ্যতের দিন/কিস্তির জন্য আগাম জমা টাকা'; }

    public function period(): string { return 'none'; }

    public function filters(): array { return ['collector', 'type']; }

    public function group(): string { return 'বকেয়া ও অগ্রিম'; }

    public function availableToCollectors(): bool { return true; }

    public function build(ReportFilters $f): ReportResult
    {
        $today = today()->toDateString();
        $adv = DB::table('installments as i')->join('accounts as a', 'a.id', '=', 'i.account_id')
            ->where('a.status', 'active')->whereDate('i.due_date', '>', $today)->where('i.amount_paid', '>', 0)
            ->when($f->type, fn ($q) => $q->where('a.type', $f->type))
            ->when($f->collectorId, fn ($q) => $q->where('a.assigned_collector_id', $f->collectorId))
            ->selectRaw('i.account_id, COUNT(*) as n, SUM(i.amount_paid) as amount, MAX(i.due_date) as through')
            ->groupBy('i.account_id')->get()->keyBy('account_id');

        $accounts = Account::query()->whereIn('id', $adv->keys())->with('member:id,name,member_code')->get()->keyBy('id');

        $rows = $adv->map(fn ($x) => [
            'account' => $accounts[$x->account_id]->account_no,
            'member' => $accounts[$x->account_id]->member->name.' ('.$accounts[$x->account_id]->member->member_code.')',
            'type' => $accounts[$x->account_id]->type->label(),
            'count' => (int) $x->n,
            'amount' => $this->m($x->amount),
            'through' => $x->through,
        ])->sortByDesc(fn ($r) => (float) $r['amount'])->values()->all();

        $totals = ['account' => 'মোট', 'count' => $this->count($rows, 'count'), 'amount' => $this->sum($rows, 'amount')];

        return new ReportResult(
            [
                new Column('account', 'অ্যাকাউন্ট'), new Column('member', 'সদস্য'), new Column('type', 'ধরন'),
                new Column('count', 'অগ্রিম দিন/কিস্তি', 'int'), new Column('amount', 'অগ্রিম টাকা', 'money'),
                new Column('through', 'যে তারিখ পর্যন্ত', 'date'),
            ],
            $rows, $totals,
            ['অ্যাকাউন্ট' => BanglaFormatter::digits(count($rows)).'টি', 'মোট অগ্রিম' => BanglaFormatter::money($totals['amount'])],
        );
    }
}
