<?php

namespace App\Services\Somiti;

use App\Enums\InstallmentStatus;
use App\Models\Account;
use App\Models\Installment;
use App\Services\CalendarService;
use App\Services\SettingService;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** Read-only figures for account pages and the register. */
class SomitiStatementService
{
    public function __construct(private CalendarService $calendar, private SettingService $settings) {}

    /** Totals for one account as of today. */
    public function summary(Account $account): array
    {
        $today = today()->toDateString();
        $rows = Installment::query()->where('account_id', $account->id)->get(['due_date', 'amount_due', 'amount_paid']);

        $paisa = fn (Collection $c, string $f) => $c->sum(fn ($r) => Money::toPaisa((string) $r->{$f}));
        $toDate = $rows->filter(fn ($r) => $r->due_date->toDateString() <= $today);
        $future = $rows->filter(fn ($r) => $r->due_date->toDateString() > $today);

        $dueToDate = $paisa($toDate, 'amount_due') - $paisa($toDate, 'amount_paid');
        $opening = Money::toPaisa((string) ($account->somitiDetail?->opening_balance ?? '0'));
        $collected = $paisa($rows, 'amount_paid');

        return [
            'scheduled_to_date' => Money::fromPaisa($paisa($toDate, 'amount_due')),
            'opening_balance' => Money::fromPaisa($opening),
            'collected' => Money::fromPaisa($collected),
            'total_saved' => Money::fromPaisa($opening + $collected),
            'due' => Money::fromPaisa(max(0, $dueToDate)),
            'advance' => Money::fromPaisa($paisa($future, 'amount_paid')),
            'due_days' => $toDate->filter(fn ($r) => Money::compare((string) $r->amount_paid, (string) $r->amount_due) < 0)->count(),
            'last_paid_date' => $rows->filter(fn ($r) => Money::toPaisa((string) $r->amount_paid) > 0)->max('due_date'),
        ];
    }

    /**
     * Day-by-day status for a month.
     *
     * @param  iterable<Account>  $accounts
     * @return array{days: list<array>, cells: array<int, array<string, string>>, totals: array<int, array>}
     */
    public function month(iterable $accounts, Carbon $month): array
    {
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();
        $ids = collect($accounts)->pluck('id')->all();

        $off = $this->settings->weeklyOffDays();
        $holidays = $this->calendar->holidaysBetween($start, $end);
        $days = [];
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $key = $d->toDateString();
            $days[] = [
                'date' => $key,
                'day' => $d->day,
                'off' => in_array($d->dayOfWeek, $off, true) || isset($holidays[$key]),
                'holiday' => $holidays[$key] ?? null,
                'future' => $d->gt(today()),
            ];
        }

        $cells = [];
        $totals = [];
        Installment::query()->whereIn('account_id', $ids)
            ->whereBetween('due_date', [$start->toDateString(), $end->toDateString()])
            ->get(['account_id', 'due_date', 'amount_due', 'amount_paid', 'status'])
            ->each(function (Installment $i) use (&$cells, &$totals) {
                $cells[$i->account_id][$i->due_date->toDateString()] = $i->status->value;
                $t = $totals[$i->account_id] ?? ['due' => 0, 'paid' => 0];
                if ($i->due_date->lte(today())) {
                    $t['due'] += Money::toPaisa((string) $i->amount_due) - Money::toPaisa((string) $i->amount_paid);
                }
                $t['paid'] += Money::toPaisa((string) $i->amount_paid);
                $totals[$i->account_id] = $t;
            });

        $totals = array_map(fn ($t) => ['due' => Money::fromPaisa(max(0, $t['due'])), 'paid' => Money::fromPaisa($t['paid'])], $totals);

        return compact('days', 'cells', 'totals');
    }

    public static function statusSymbol(?string $status): string
    {
        return match ($status) {
            InstallmentStatus::Paid->value => '✓',
            InstallmentStatus::Partial->value => '◐',
            InstallmentStatus::Unpaid->value => '•',
            default => '',
        };
    }
}
