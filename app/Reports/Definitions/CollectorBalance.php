<?php

namespace App\Reports\Definitions;

use App\Models\CashHandover;
use App\Models\User;
use App\Reports\Column;
use App\Reports\Report;
use App\Reports\ReportFilters;
use App\Reports\ReportResult;
use App\Support\BanglaFormatter;
use Illuminate\Support\Facades\DB;

class CollectorBalance extends Report
{
    public function key(): string { return 'collector-balance'; }

    public function title(): string { return 'কালেক্টর ব্যালেন্স রিপোর্ট'; }

    public function description(): string { return 'নির্দিষ্ট দিনের শেষে প্রত্যেকের হাতে থাকা টাকা'; }

    public function period(): string { return 'date'; }

    public function filters(): array { return []; }

    public function group(): string { return 'ক্যাশ'; }

    public function build(ReportFilters $f): ReportResult
    {
        $end = $f->from->copy()->endOfDay();
        $bal = DB::table('collector_cash_ledger')->where('transaction_datetime', '<=', $end)
            ->selectRaw('collector_id, SUM(credit) - SUM(debit) as bal, MAX(transaction_datetime) as last')
            ->groupBy('collector_id')->get()->keyBy('collector_id');
        $out = CashHandover::query()->whereIn('status', \App\Enums\HandoverStatus::reserving())
            ->selectRaw('sender_collector_id as uid, SUM(amount) as s')->groupBy('sender_collector_id')->pluck('s', 'uid');
        $in = CashHandover::query()->where('status', 'pending')
            ->selectRaw('receiver_collector_id as uid, SUM(amount) as s')->groupBy('receiver_collector_id')->pluck('s', 'uid');

        $rows = User::query()->cashHolders()->orderBy('role')->orderBy('name')->get()
            ->filter(fn (User $u) => $u->is_active || isset($bal[$u->id]))
            ->map(fn (User $u) => [
                'name' => $u->name.($u->isAdmin() ? ' (এডমিন)' : ''),
                'status' => $u->is_active ? 'সক্রিয়' : 'নিষ্ক্রিয়',
                'balance' => $this->m($bal[$u->id]->bal ?? 0),
                'pending_out' => $this->m($out[$u->id] ?? 0),
                'pending_in' => $this->m($in[$u->id] ?? 0),
                'last' => $bal[$u->id]->last ?? null,
            ])->values()->all();

        $totals = ['name' => 'মোট', 'balance' => $this->sum($rows, 'balance'), 'pending_out' => $this->sum($rows, 'pending_out'), 'pending_in' => $this->sum($rows, 'pending_in')];

        return new ReportResult(
            [
                new Column('name', 'নাম'), new Column('status', 'অবস্থা'), new Column('balance', 'হাতে থাকা টাকা', 'money'),
                new Column('pending_out', 'অপেক্ষমাণ প্রদান', 'money'), new Column('pending_in', 'অপেক্ষমাণ গ্রহণ', 'money'),
                new Column('last', 'শেষ লেনদেন', 'datetime'),
            ],
            $rows, $totals,
            ['সবার হাতে মোট' => BanglaFormatter::money($totals['balance'])],
            'অপেক্ষমাণ কলামগুলো আজকের অবস্থা দেখায়।',
        );
    }
}
