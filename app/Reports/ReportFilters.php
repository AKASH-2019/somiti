<?php

namespace App\Reports;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Normalised filter values. For a collector the collector filter is always forced to themselves,
 * so every report a collector opens is limited to their own money and accounts.
 */
final class ReportFilters
{
    public function __construct(
        public User $viewer,
        public Carbon $from,
        public Carbon $to,
        public ?int $collectorId,
        public ?string $type,
    ) {}

    public static function fromRequest(Request $request, Report $report): self
    {
        $viewer = $request->user();
        $date = fn (?string $v) => $v && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? Carbon::parse($v)->startOfDay() : null;
        [$defFrom, $defTo] = $report->defaultPeriod();

        switch ($report->period()) {
            case 'month':
                $m = (string) $request->query('month');
                $from = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $m) ? Carbon::parse($m.'-01')->startOfDay() : $defFrom->copy()->startOfMonth();
                $to = $from->copy()->endOfMonth()->startOfDay();
                break;
            case 'date':
                $from = $to = $date($request->query('date')) ?? $defFrom;
                break;
            case 'range':
                $from = $date($request->query('from')) ?? $defFrom;
                $to = $date($request->query('to')) ?? $defTo;
                if ($to->lt($from)) {
                    [$from, $to] = [$to, $from];
                }
                if ($from->diffInDays($to) > 366) {
                    $from = $to->copy()->subDays(366);
                }
                break;
            default: // none
                $from = $to = today();
        }

        $collector = $viewer->isAdmin()
            ? (ctype_digit((string) $request->query('collector')) ? (int) $request->query('collector') : null)
            : $viewer->id;
        $type = in_array($request->query('type'), ['somiti', 'kisti'], true) ? $request->query('type') : null;

        return new self($viewer, $from->copy(), $to->copy(), $collector, $type);
    }

    public function query(): array
    {
        return array_filter([
            'from' => $this->from->toDateString(),
            'to' => $this->to->toDateString(),
            'date' => $this->from->toDateString(),
            'month' => $this->from->format('Y-m'),
            'collector' => $this->viewer->isAdmin() ? $this->collectorId : null,
            'type' => $this->type,
        ]);
    }
}
