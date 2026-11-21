<?php

namespace App\Services;

use App\Enums\HandoverStatus;
use App\Models\Account;
use App\Models\CashHandover;
use App\Models\Member;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Cash\CashLedgerService;
use App\Services\Cash\HandoverService;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/** Figures for the two dashboards. $collector = null means the whole organisation. */
class DashboardService
{
    public function __construct(private CashLedgerService $ledger, private HandoverService $handovers) {}

    /** Today's cash collection split by account type. */
    public function todayCollection(?User $collector = null): array
    {
        $rows = DB::table('transactions as t')->join('accounts as a', 'a.id', '=', 't.account_id')
            ->where('t.status', 'completed')->where('t.entry_type', 'cash')
            ->whereBetween('t.collected_at', [today()->startOfDay(), today()->endOfDay()])
            ->when($collector, fn ($q) => $q->where('t.collector_id', $collector->id))
            ->selectRaw('a.type, SUM(t.amount) as total, COUNT(*) as n')->groupBy('a.type')->get()->keyBy('type');

        $somiti = $this->m($rows['somiti']->total ?? 0);
        $kisti = $this->m($rows['kisti']->total ?? 0);

        return [
            'somiti' => $somiti,
            'kisti' => $kisti,
            'total' => Money::add($somiti, $kisti),
            'receipts' => (int) (($rows['somiti']->n ?? 0) + ($rows['kisti']->n ?? 0)),
        ];
    }

    /** Dues (to date) and advance (future rows already paid) on active accounts. */
    public function duesAndAdvance(?User $collector = null): array
    {
        $today = today()->toDateString();
        $row = DB::table('installments as i')->join('accounts as a', 'a.id', '=', 'i.account_id')
            ->where('a.status', 'active')
            ->when($collector, fn ($q) => $q->where('a.assigned_collector_id', $collector->id))
            ->selectRaw('SUM(CASE WHEN i.due_date <= ? AND i.amount_paid < i.amount_due THEN i.amount_due - i.amount_paid ELSE 0 END) as due,
                         SUM(CASE WHEN i.due_date > ? THEN i.amount_paid ELSE 0 END) as advance', [$today, $today])
            ->first();

        return ['due' => $this->m($row->due ?? 0), 'advance' => $this->m($row->advance ?? 0)];
    }

    /** Last N days of cash collection, oldest first, for the bar chart. */
    public function lastDays(int $days = 7, ?User $collector = null): array
    {
        $from = today()->subDays($days - 1);
        $sums = DB::table('transactions')->where('status', 'completed')->where('entry_type', 'cash')
            ->whereBetween('collected_at', [$from->copy()->startOfDay(), today()->endOfDay()])
            ->when($collector, fn ($q) => $q->where('collector_id', $collector->id))
            ->selectRaw('DATE(collected_at) as d, SUM(amount) as total')->groupByRaw('DATE(collected_at)')->pluck('total', 'd');

        $out = [];
        for ($d = $from->copy(); $d->lte(today()); $d->addDay()) {
            $out[] = ['date' => $d->copy(), 'total' => $this->m($sums[$d->toDateString()] ?? 0)];
        }

        return $out;
    }

    public function admin(): array
    {
        $pending = CashHandover::query()->whereIn('status', [HandoverStatus::Pending->value, HandoverStatus::Disputed->value]);

        // Today's activity per cash holder
        $today = DB::table('transactions as t')->join('accounts as a', 'a.id', '=', 't.account_id')
            ->where('t.status', 'completed')->where('t.entry_type', 'cash')
            ->whereBetween('t.collected_at', [today()->startOfDay(), today()->endOfDay()])
            ->selectRaw('t.collector_id, a.type, SUM(t.amount) as total, COUNT(*) as n, MAX(t.collected_at) as last')
            ->groupBy('t.collector_id', 'a.type')->get()->groupBy('collector_id');
        $balances = DB::table('collector_cash_ledger')->selectRaw('collector_id, SUM(credit) - SUM(debit) as bal')
            ->groupBy('collector_id')->pluck('bal', 'collector_id');

        $activity = User::query()->cashHolders()->active()->orderBy('role')->orderBy('name')->get()
            ->map(function (User $u) use ($today, $balances) {
                $g = $today[$u->id] ?? collect();
                $s = $this->m($g->firstWhere('type', 'somiti')?->total ?? 0);
                $k = $this->m($g->firstWhere('type', 'kisti')?->total ?? 0);

                return (object) [
                    'user' => $u,
                    'receipts' => (int) $g->sum('n'),
                    'somiti' => $s,
                    'kisti' => $k,
                    'total' => Money::add($s, $k),
                    'last' => $g->max('last'),
                    'balance' => $this->m($balances[$u->id] ?? 0),
                ];
            });

        return [
            'members' => Member::query()->count(),
            'activeMembers' => Member::query()->where('status', 'active')->count(),
            'somitiAccounts' => Account::query()->somiti()->where('status', 'active')->count(),
            'kistiAccounts' => Account::query()->kisti()->where('status', 'active')->count(),
            'collectors' => User::collectors()->count(),
            'activeCollectors' => User::collectors()->active()->count(),
            'today' => $this->todayCollection(),
            'dues' => $this->duesAndAdvance(),
            'cashInHands' => $this->m($balances->sum()),
            'pendingCount' => (clone $pending)->count(),
            'pendingAmount' => $this->m((clone $pending)->sum('amount')),
            'disputed' => CashHandover::query()->where('status', HandoverStatus::Disputed->value)->count(),
            'activity' => $activity,
            'week' => $this->lastDays(),
        ];
    }

    public function collector(User $user): array
    {
        $dueMembers = DB::table('installments as i')->join('accounts as a', 'a.id', '=', 'i.account_id')
            ->join('members as m', 'm.id', '=', 'a.member_id')
            ->where('a.status', 'active')->where('a.assigned_collector_id', $user->id)
            ->whereDate('i.due_date', '<=', today()->toDateString())->whereColumn('i.amount_paid', '<', 'i.amount_due')
            ->selectRaw('m.id, m.name, m.member_code, m.mobile, SUM(i.amount_due - i.amount_paid) as due, COUNT(*) as n')
            ->groupBy('m.id', 'm.name', 'm.member_code', 'm.mobile')
            ->orderByDesc('due')->get();

        return [
            'members' => $user->members()->count(),
            'activeMembers' => $user->members()->where('status', 'active')->count(),
            'today' => $this->todayCollection($user),
            'dues' => $this->duesAndAdvance($user),
            'balance' => $this->ledger->balance($user),
            'available' => $this->handovers->available($user),
            'outgoing' => CashHandover::query()->where('sender_collector_id', $user->id)->whereIn('status', HandoverStatus::reserving())->with('receiver:id,name')->get(),
            'incoming' => CashHandover::query()->where('receiver_collector_id', $user->id)->where('status', HandoverStatus::Pending->value)->with('sender:id,name')->get(),
            'dueMemberCount' => $dueMembers->count(),
            'dueMembers' => $dueMembers->take(8),
            'recent' => Transaction::query()->where('collector_id', $user->id)
                ->with(['member:id,name', 'account:id,account_no,type'])
                ->orderByDesc('collected_at')->orderByDesc('id')->limit(8)->get(),
            'week' => $this->lastDays(7, $user),
        ];
    }

    private function m(mixed $v): string
    {
        return number_format((float) $v, 2, '.', '');
    }
}
