<?php

namespace App\Services\Kisti;

use App\Models\Account;
use App\Models\Installment;
use App\Support\Money;

class KistiStatementService
{
    public function summary(Account $account): array
    {
        $today = today()->toDateString();
        $rows = Installment::query()->where('account_id', $account->id)->orderBy('sequence_no')
            ->get(['id', 'sequence_no', 'due_date', 'amount_due', 'amount_paid', 'status']);

        $p = fn ($v) => Money::toPaisa((string) $v);
        $total = $rows->sum(fn ($r) => $p($r->amount_due));
        $paid = $rows->sum(fn ($r) => $p($r->amount_paid));
        $overdueRows = $rows->filter(fn ($r) => $r->due_date->toDateString() <= $today && $p($r->amount_paid) < $p($r->amount_due));
        $overdue = $overdueRows->sum(fn ($r) => $p($r->amount_due) - $p($r->amount_paid));
        $advance = $rows->filter(fn ($r) => $r->due_date->toDateString() > $today)->sum(fn ($r) => $p($r->amount_paid));
        $next = $rows->first(fn ($r) => $r->due_date->toDateString() >= $today && $p($r->amount_paid) < $p($r->amount_due));

        return [
            'total' => Money::fromPaisa($total),
            'paid' => Money::fromPaisa($paid),
            'remaining' => Money::fromPaisa($total - $paid),
            'overdue' => Money::fromPaisa($overdue),
            'overdue_count' => $overdueRows->count(),
            'advance' => Money::fromPaisa($advance),
            'paid_count' => $rows->filter(fn ($r) => $p($r->amount_paid) >= $p($r->amount_due))->count(),
            'next' => $next,
            'rows' => $rows,
        ];
    }
}
