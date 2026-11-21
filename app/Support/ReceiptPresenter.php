<?php

namespace App\Support;

use App\Enums\AccountType;
use App\Models\Transaction;
use App\Models\TransactionAllocation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Turns a transaction's allocations into short Bengali lines for receipts and lists. */
final class ReceiptPresenter
{
    /** @return list<string> */
    public static function covered(Transaction $tx): array
    {
        $allocs = $tx->allocations->load('installment');

        return $tx->account->type === AccountType::Somiti ? self::dateRuns($allocs->all()) : self::kistiRuns($allocs->all());
    }

    /** "১৯ – ২৩ নভেম্বর ২০২৬ (৫ দিন)" — consecutive calendar days are merged. */
    private static function dateRuns(array $allocs): array
    {
        $dates = array_map(fn (TransactionAllocation $a) => $a->covered_date->copy(), $allocs);
        usort($dates, fn ($a, $b) => $a <=> $b);
        $runs = [];
        foreach ($dates as $d) {
            $last = array_key_last($runs);
            if ($last !== null && $runs[$last][1]->copy()->addDay()->isSameDay($d)) {
                $runs[$last][1] = $d;
            } else {
                $runs[] = [$d, $d];
            }
        }

        return array_map(function ($run) {
            [$from, $to] = $run;
            if ($from->isSameDay($to)) {
                return BanglaFormatter::date($from);
            }
            $days = $from->diffInDays($to) + 1;
            $left = $from->isSameMonth($to) ? BanglaFormatter::digits($from->day)
                : BanglaFormatter::digits($from->day).' '.BanglaFormatter::MONTHS[$from->month].($from->isSameYear($to) ? '' : ' '.BanglaFormatter::digits($from->year));

            return $left.' – '.BanglaFormatter::date($to).' ('.BanglaFormatter::digits((int) $days).' দিন)';
        }, $runs);
    }

    /** "কিস্তি নং ৫ – ৯", partial ones listed with their amount. */
    private static function kistiRuns(array $allocs): array
    {
        $full = [];
        $lines = [];
        foreach ($allocs as $a) {
            $seq = $a->installment->sequence_no;
            if (Money::compare((string) $a->amount, (string) $a->installment->amount_due) < 0) {
                $lines[] = 'কিস্তি নং '.BanglaFormatter::digits($seq).' ('.BanglaFormatter::date($a->covered_date).') — আংশিক '.BanglaFormatter::money((string) $a->amount);
            } else {
                $full[] = $seq;
            }
        }
        sort($full);
        $runs = [];
        foreach ($full as $n) {
            $last = array_key_last($runs);
            if ($last !== null && $runs[$last][1] + 1 === $n) {
                $runs[$last][1] = $n;
            } else {
                $runs[] = [$n, $n];
            }
        }
        $out = array_map(fn ($r) => $r[0] === $r[1]
            ? 'কিস্তি নং '.BanglaFormatter::digits($r[0])
            : 'কিস্তি নং '.BanglaFormatter::digits($r[0]).' – '.BanglaFormatter::digits($r[1]).' ('.BanglaFormatter::digits($r[1] - $r[0] + 1).'টি)', $runs);

        return [...$out, ...$lines];
    }

    /** Total paid on the account up to and including this receipt (completed transactions only). */
    public static function paidThrough(Transaction $tx): string
    {
        $sum = DB::table('transaction_allocations as a')
            ->join('transactions as t', 't.id', '=', 'a.transaction_id')
            ->where('t.account_id', $tx->account_id)->where('t.id', '<=', $tx->id)
            ->where('t.status', 'completed')->where('a.status', 'active')
            ->sum('a.amount');

        return number_format((float) $sum, 2, '.', '');
    }
}
