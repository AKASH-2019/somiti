<?php

namespace App\Reports;

use App\Models\User;
use App\Reports\Definitions;

final class ReportRegistry
{
    /** In the order shown on the reports page (numbering follows the project brief). */
    public const REPORTS = [
        Definitions\DailySomitiCollection::class,
        Definitions\DailyKistiCollection::class,
        Definitions\WeeklyCollection::class,
        Definitions\MonthlyCollection::class,
        Definitions\MemberAccounts::class,
        Definitions\CollectorCollection::class,
        Definitions\SomitiShares::class,
        Definitions\KistiDues::class,
        Definitions\AdvanceDeposits::class,
        Definitions\CollectorCash::class,
        Definitions\HandoverReport::class,
        Definitions\CollectorBalance::class,
        Definitions\DailyCashClosing::class,
    ];

    /** @return array<string, Report> */
    public static function for(User $user): array
    {
        $out = [];
        foreach (self::REPORTS as $class) {
            $r = app($class);
            if ($user->isAdmin() || $r->availableToCollectors()) {
                $out[$r->key()] = $r;
            }
        }

        return $out;
    }

    public static function find(User $user, string $key): ?Report
    {
        return self::for($user)[$key] ?? null;
    }
}
