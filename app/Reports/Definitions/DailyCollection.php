<?php

namespace App\Reports\Definitions;

use App\Models\Transaction;
use App\Reports\Column;
use App\Reports\Report;
use App\Reports\ReportFilters;
use App\Reports\ReportResult;
use App\Support\BanglaFormatter;
use App\Support\ReceiptPresenter;

/** Every cash receipt of one day for one account type. */
abstract class DailyCollection extends Report
{
    abstract protected function accountType(): string;

    public function availableToCollectors(): bool
    {
        return true;
    }

    public function landscape(): bool
    {
        return true;
    }

    public function build(ReportFilters $f): ReportResult
    {
        $txs = Transaction::query()
            ->where('status', 'completed')->where('entry_type', 'cash')
            ->whereBetween('collected_at', [$f->from->copy()->startOfDay(), $f->to->copy()->endOfDay()])
            ->whereHas('account', fn ($q) => $q->where('type', $this->accountType()))
            ->when($f->collectorId, fn ($q) => $q->where('collector_id', $f->collectorId))
            ->with(['member:id,name,member_code', 'account', 'collector:id,name', 'allocations.installment'])
            ->orderBy('collected_at')->orderBy('id')->get();

        $rows = $txs->map(fn (Transaction $t) => [
            'receipt' => $t->receipt_no,
            'time' => $t->collected_at,
            'member' => $t->member->name.' ('.$t->member->member_code.')',
            'account' => $t->account->account_no,
            'collector' => $t->collector->name,
            'covered' => implode('; ', ReceiptPresenter::covered($t)),
            'amount' => $this->m($t->amount),
        ])->all();

        $total = $this->sum($rows, 'amount');

        return new ReportResult(
            [
                new Column('receipt', 'রসিদ নম্বর'),
                new Column('time', 'সময়', 'datetime'),
                new Column('member', 'সদস্য'),
                new Column('account', 'অ্যাকাউন্ট'),
                new Column('collector', 'কালেক্টর'),
                new Column('covered', $this->accountType() === 'somiti' ? 'যে তারিখের জমা' : 'যে কিস্তির জমা'),
                new Column('amount', 'টাকা', 'money'),
            ],
            $rows,
            ['receipt' => 'মোট', 'amount' => $total],
            ['রসিদ সংখ্যা' => BanglaFormatter::digits(count($rows)).'টি', 'মোট সংগ্রহ' => BanglaFormatter::money($total)],
            'শুধু নগদ সংগ্রহ; বাতিলকৃত লেনদেন ও পুরনো খাতার এন্ট্রি বাদে।',
        );
    }
}
