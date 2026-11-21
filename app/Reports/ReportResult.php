<?php

namespace App\Reports;

/** What a report returns. Rows hold raw values; formatting happens per output (screen/PDF in Bengali, Excel raw). */
final class ReportResult
{
    /**
     * @param  list<Column>  $columns
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, mixed>|null  $totals  keyed like rows; first text column is labelled "মোট"
     * @param  array<string, string>  $summary  label => already-formatted value
     */
    public function __construct(
        public array $columns,
        public array $rows,
        public ?array $totals = null,
        public array $summary = [],
        public ?string $note = null,
    ) {}
}
