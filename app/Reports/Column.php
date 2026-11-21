<?php

namespace App\Reports;

/** A report column. type: text | money | int | date | datetime */
final class Column
{
    public function __construct(
        public string $key,
        public string $label,
        public string $type = 'text',
    ) {}

    public function isNumeric(): bool
    {
        return in_array($this->type, ['money', 'int'], true);
    }
}
