<?php

namespace App\Services\Cash;

use App\Enums\HandoverStatus;
use App\Enums\LedgerType;
use App\Models\CashHandover;
use App\Models\CashLedgerEntry;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class CashStatementService
{
    public function __construct(private CashLedgerService $ledger, private HandoverService $handovers) {}

    /** Ledger rows for a period with running balance; opening = balance before the period. */
    public function statement(User $holder, Carbon $from, Carbon $to): array
    {
        $p = fn ($v) => Money::toPaisa(number_format((float) $v, 2, '.', ''));
        $before = CashLedgerEntry::query()->where('collector_id', $holder->id)->where('transaction_datetime', '<', $from->copy()->startOfDay())
            ->selectRaw('COALESCE(SUM(credit),0) c, COALESCE(SUM(debit),0) d')->first();
        $running = $p($before->c) - $p($before->d);
        $opening = $running;

        $rows = CashLedgerEntry::query()->where('collector_id', $holder->id)
            ->whereBetween('transaction_datetime', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->orderBy('transaction_datetime')->orderBy('id')->get()
            ->map(function (CashLedgerEntry $e) use (&$running, $p) {
                $running += $p($e->credit) - $p($e->debit);
                $e->setAttribute('running', Money::fromPaisa($running));

                return $e;
            });

        $in = $rows->sum(fn ($e) => $p($e->credit));
        $out = $rows->sum(fn ($e) => $p($e->debit));

        return [
            'opening' => Money::fromPaisa($opening),
            'rows' => $rows,
            'in' => Money::fromPaisa($in),
            'out' => Money::fromPaisa($out),
            'closing' => Money::fromPaisa($running),
        ];
    }

    /** One line per cash holder for the admin overview. */
    public function overview(): Collection
    {
        $p = fn ($v) => Money::toPaisa(number_format((float) $v, 2, '.', ''));
        $sums = CashLedgerEntry::query()
            ->selectRaw('collector_id, transaction_type, COALESCE(SUM(credit),0) c, COALESCE(SUM(debit),0) d')
            ->groupBy('collector_id', 'transaction_type')->get()->groupBy('collector_id');
        $pendingOut = CashHandover::query()->whereIn('status', HandoverStatus::reserving())
            ->selectRaw('sender_collector_id as uid, SUM(amount) s')->groupBy('sender_collector_id')->pluck('s', 'uid');
        $pendingIn = CashHandover::query()->where('status', HandoverStatus::Pending->value)
            ->selectRaw('receiver_collector_id as uid, SUM(amount) s')->groupBy('receiver_collector_id')->pluck('s', 'uid');

        return User::query()->cashHolders()->orderByDesc('is_active')->orderBy('role')->orderBy('name')->get()
            ->map(function (User $u) use ($sums, $pendingOut, $pendingIn, $p) {
                $t = fn (LedgerType $type, string $side) => $p(($sums[$u->id] ?? collect())->firstWhere('transaction_type', $type)?->{$side} ?? 0);
                $collection = $t(LedgerType::Collection, 'c') - $t(LedgerType::CollectionReversal, 'd');
                $adjust = $t(LedgerType::Adjustment, 'c') - $t(LedgerType::Adjustment, 'd');
                $all = ($sums[$u->id] ?? collect());
                $balance = $all->sum(fn ($r) => $p($r->c)) - $all->sum(fn ($r) => $p($r->d));

                return (object) [
                    'user' => $u,
                    'opening' => Money::fromPaisa($t(LedgerType::Opening, 'c')),
                    'collection' => Money::fromPaisa($collection),
                    'handover_in' => Money::fromPaisa($t(LedgerType::HandoverIn, 'c')),
                    'handover_out' => Money::fromPaisa($t(LedgerType::HandoverOut, 'd')),
                    'adjustment' => Money::fromPaisa($adjust),
                    'balance' => Money::fromPaisa($balance),
                    'pending_out' => Money::fromPaisa($p($pendingOut[$u->id] ?? 0)),
                    'pending_in' => Money::fromPaisa($p($pendingIn[$u->id] ?? 0)),
                ];
            });
    }
}
