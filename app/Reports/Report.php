<?php

namespace App\Reports;

use App\Models\User;
use App\Support\BanglaFormatter;
use App\Support\Money;
use Illuminate\Support\Carbon;

abstract class Report
{
    abstract public function key(): string;

    abstract public function title(): string;

    abstract public function description(): string;

    abstract public function build(ReportFilters $f): ReportResult;

    /** none | date | range | month */
    public function period(): string
    {
        return 'date';
    }

    /** @return array{0: Carbon, 1: Carbon} */
    public function defaultPeriod(): array
    {
        return [today(), today()];
    }

    /** Which extra filters to show: collector, type */
    public function filters(): array
    {
        return ['collector'];
    }

    public function availableToCollectors(): bool
    {
        return false;
    }

    public function landscape(): bool
    {
        return false;
    }

    /** "group" heading on the reports page */
    public function group(): string
    {
        return 'সংগ্রহ';
    }

    public function periodLabel(ReportFilters $f): string
    {
        return match ($this->period()) {
            'month' => BanglaFormatter::MONTHS[$f->from->month].' '.BanglaFormatter::digits($f->from->year),
            'range' => $f->from->isSameDay($f->to) ? BanglaFormatter::date($f->from) : BanglaFormatter::date($f->from).' – '.BanglaFormatter::date($f->to),
            'date' => BanglaFormatter::date($f->from, true),
            default => BanglaFormatter::date(today()).' পর্যন্ত',
        };
    }

    public function scopeLabel(ReportFilters $f): ?string
    {
        if (! $f->collectorId) {
            return null;
        }

        return 'কালেক্টর: '.(User::query()->find($f->collectorId)?->name ?? '—');
    }

    // ---- helpers for subclasses ------------------------------------------------------------

    protected function m(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    /** Sum a money column of rows exactly. */
    protected function sum(array $rows, string $key): string
    {
        return Money::fromPaisa(array_sum(array_map(fn ($r) => Money::toPaisa($this->m($r[$key] ?? 0)), $rows)));
    }

    protected function count(array $rows, string $key): int
    {
        return (int) array_sum(array_map(fn ($r) => (int) ($r[$key] ?? 0), $rows));
    }
}
