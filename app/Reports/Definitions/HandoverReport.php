<?php

namespace App\Reports\Definitions;

use App\Enums\HandoverStatus;
use App\Models\CashHandover;
use App\Reports\Column;
use App\Reports\Report;
use App\Reports\ReportFilters;
use App\Reports\ReportResult;
use App\Support\BanglaFormatter;

class HandoverReport extends Report
{
    public function key(): string { return 'handovers'; }

    public function title(): string { return 'টাকা হস্তান্তর রিপোর্ট'; }

    public function description(): string { return 'নির্দিষ্ট সময়ের সব হস্তান্তর ও তার অবস্থা'; }

    public function period(): string { return 'range'; }

    public function defaultPeriod(): array { return [today()->startOfMonth(), today()]; }

    public function group(): string { return 'ক্যাশ'; }

    public function availableToCollectors(): bool { return true; }

    public function landscape(): bool { return true; }

    public function build(ReportFilters $f): ReportResult
    {
        $list = CashHandover::query()->with(['sender:id,name', 'receiver:id,name'])
            ->whereBetween('handover_datetime', [$f->from->copy()->startOfDay(), $f->to->copy()->endOfDay()])
            ->when($f->collectorId, fn ($q) => $q->where(fn ($w) => $w->where('sender_collector_id', $f->collectorId)->orWhere('receiver_collector_id', $f->collectorId)))
            ->orderBy('handover_datetime')->orderBy('id')->get();

        $rows = $list->map(fn (CashHandover $h) => [
            'number' => $h->handover_number,
            'time' => $h->handover_datetime,
            'sender' => $h->sender->name,
            'receiver' => $h->receiver->name,
            'amount' => (string) $h->amount,
            'status' => $h->status->label(),
            'done' => $h->received_datetime ?? $h->resolved_at ?? $h->cancelled_at,
        ])->all();

        $summary = ['মোট হস্তান্তর' => BanglaFormatter::digits(count($rows)).'টি'];
        foreach (HandoverStatus::cases() as $s) {
            $g = $list->where('status', $s);
            if ($g->isNotEmpty()) {
                $summary[$s->label()] = BanglaFormatter::digits($g->count()).'টি, '.BanglaFormatter::money($this->m($g->sum('amount')));
            }
        }
        $received = $list->where('status', HandoverStatus::Received);

        return new ReportResult(
            [
                new Column('number', 'হস্তান্তর নম্বর'), new Column('time', 'তারিখ ও সময়', 'datetime'),
                new Column('sender', 'প্রেরক'), new Column('receiver', 'প্রাপক'), new Column('amount', 'টাকা', 'money'),
                new Column('status', 'অবস্থা'), new Column('done', 'নিশ্চিত/নিষ্পত্তি', 'datetime'),
            ],
            $rows,
            ['number' => 'গৃহীত মোট', 'amount' => $this->m($received->sum('amount'))],
            $summary,
        );
    }
}
