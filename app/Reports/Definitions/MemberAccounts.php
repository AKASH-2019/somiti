<?php

namespace App\Reports\Definitions;

use App\Models\Member;
use App\Reports\Column;
use App\Reports\Report;
use App\Reports\ReportFilters;
use App\Reports\ReportResult;
use App\Support\BanglaFormatter;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

class MemberAccounts extends Report
{
    public function key(): string { return 'member-accounts'; }

    public function title(): string { return 'সদস্যভিত্তিক হিসাব'; }

    public function description(): string { return 'প্রত্যেক সদস্যের সঞ্চয়, ঋণ, আদায় ও বকেয়া'; }

    public function period(): string { return 'none'; }

    public function group(): string { return 'হিসাব'; }

    public function availableToCollectors(): bool { return true; }

    public function landscape(): bool { return true; }

    public function build(ReportFilters $f): ReportResult
    {
        $today = today()->toDateString();
        $members = Member::query()->with('collector:id,name')
            ->when($f->collectorId, fn ($q) => $q->where('collector_id', $f->collectorId))
            ->orderBy('member_code')->get();
        $ids = $members->pluck('id');

        $agg = DB::table('installments as i')->join('accounts as a', 'a.id', '=', 'i.account_id')
            ->whereIn('a.member_id', $ids)->where('a.status', '!=', 'cancelled')
            ->selectRaw('a.member_id, a.type, SUM(i.amount_paid) as paid, SUM(i.amount_due) as total,
                SUM(CASE WHEN i.due_date <= ? THEN i.amount_due - i.amount_paid ELSE 0 END) as due', [$today])
            ->groupBy('a.member_id', 'a.type')->get()->groupBy('member_id');
        $counts = DB::table('accounts')->whereIn('member_id', $ids)->where('status', 'active')
            ->selectRaw('member_id, type, COUNT(*) as n')->groupBy('member_id', 'type')->get()->groupBy('member_id');
        $opening = DB::table('somiti_account_details as d')->join('accounts as a', 'a.id', '=', 'd.account_id')
            ->whereIn('a.member_id', $ids)->where('a.status', '!=', 'cancelled')
            ->selectRaw('a.member_id, SUM(d.opening_balance) as ob')->groupBy('a.member_id')->pluck('ob', 'member_id');

        $rows = $members->map(function (Member $m) use ($agg, $counts, $opening) {
            $s = ($agg[$m->id] ?? collect())->firstWhere('type', 'somiti');
            $k = ($agg[$m->id] ?? collect())->firstWhere('type', 'kisti');
            $c = $counts[$m->id] ?? collect();

            return [
                'code' => $m->member_code,
                'name' => $m->name,
                'business' => $m->business_name,
                'collector' => $m->collector?->name,
                'somiti_accounts' => (int) ($c->firstWhere('type', 'somiti')?->n ?? 0),
                'saved' => Money::add($this->m($opening[$m->id] ?? 0), $this->m($s?->paid ?? 0)),
                'somiti_due' => $this->m(max(0, (float) ($s?->due ?? 0))),
                'kisti_accounts' => (int) ($c->firstWhere('type', 'kisti')?->n ?? 0),
                'kisti_paid' => $this->m($k?->paid ?? 0),
                'kisti_left' => Money::sub($this->m($k?->total ?? 0), $this->m($k?->paid ?? 0)),
                'kisti_due' => $this->m(max(0, (float) ($k?->due ?? 0))),
            ];
        })->all();

        $totals = ['code' => 'মোট'];
        foreach (['saved', 'somiti_due', 'kisti_paid', 'kisti_left', 'kisti_due'] as $key) {
            $totals[$key] = $this->sum($rows, $key);
        }
        $totals['somiti_accounts'] = $this->count($rows, 'somiti_accounts');
        $totals['kisti_accounts'] = $this->count($rows, 'kisti_accounts');

        return new ReportResult(
            [
                new Column('code', 'সদস্য আইডি'), new Column('name', 'নাম'), new Column('business', 'প্রতিষ্ঠান'),
                new Column('collector', 'কালেক্টর'),
                new Column('somiti_accounts', 'সমিতি অ্যাকাউন্ট', 'int'), new Column('saved', 'মোট সঞ্চয়', 'money'),
                new Column('somiti_due', 'সমিতি বকেয়া', 'money'),
                new Column('kisti_accounts', 'কিস্তি অ্যাকাউন্ট', 'int'), new Column('kisti_paid', 'কিস্তি আদায়', 'money'),
                new Column('kisti_left', 'অবশিষ্ট ঋণ', 'money'), new Column('kisti_due', 'কিস্তি বকেয়া', 'money'),
            ],
            $rows,
            $totals,
            [
                'সদস্য' => BanglaFormatter::digits(count($rows)).' জন',
                'মোট সঞ্চয়' => BanglaFormatter::money($totals['saved']),
                'মোট বকেয়া' => BanglaFormatter::money(Money::add($totals['somiti_due'], $totals['kisti_due'])),
                'অবশিষ্ট ঋণ' => BanglaFormatter::money($totals['kisti_left']),
            ],
            'মোট সঞ্চয় = প্রারম্ভিক সঞ্চয় + সমিতি জমা। বকেয়া আজ পর্যন্ত।',
        );
    }
}
