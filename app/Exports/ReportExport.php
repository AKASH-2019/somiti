<?php

namespace App\Exports;

use App\Reports\Report;
use App\Reports\ReportFilters;
use App\Reports\ReportFormatter;
use App\Reports\ReportResult;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;

/** Excel sheet: title rows, Bengali headings, raw numbers (so totals can be recalculated in Excel). */
class ReportExport implements FromArray, ShouldAutoSize, WithTitle
{
    public function __construct(private Report $report, private ReportFilters $filters, private ReportResult $result) {}

    public function array(): array
    {
        $cols = $this->result->columns;
        $out = [
            [setting('org_name')],
            [$this->report->title().' — '.$this->report->periodLabel($this->filters)],
        ];
        if ($scope = $this->report->scopeLabel($this->filters)) {
            $out[] = [$scope];
        }
        $out[] = [];
        $out[] = array_map(fn ($c) => $c->label, $cols);
        foreach ($this->result->rows as $row) {
            $out[] = array_map(fn ($c) => ReportFormatter::excel($row[$c->key] ?? null, $c), $cols);
        }
        if ($this->result->totals) {
            $out[] = array_map(fn ($c) => array_key_exists($c->key, $this->result->totals)
                ? ($c->isNumeric() ? ReportFormatter::excel($this->result->totals[$c->key], $c) : $this->result->totals[$c->key])
                : '', $cols);
        }
        if ($this->result->summary) {
            $out[] = [];
            foreach ($this->result->summary as $label => $value) {
                $out[] = [$label, $value];
            }
        }

        return $out;
    }

    public function title(): string
    {
        return mb_substr($this->report->title(), 0, 31);
    }
}
