<?php

namespace App\Services\Kisti;

use App\Enums\AccountStatus;
use App\Enums\AccountType;
use App\Enums\KistiFrequency;
use App\Models\Account;
use App\Models\Installment;
use App\Models\Member;
use App\Models\User;
use App\Services\AuditService;
use App\Services\NumberSequenceService;
use App\Support\Money;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Facades\DB;

class KistiAccountService
{
    public const MAX_INSTALLMENTS = 1000;

    public function __construct(
        private NumberSequenceService $sequences,
        private KistiScheduleService $schedule,
        private AuditService $audit,
    ) {}

    /**
     * Works out the installment count and the last installment amount.
     *
     * @return array{total: string, count: int, last: string}
     */
    public static function terms(string $loan, string $charge, string $installment): array
    {
        $total = Money::toPaisa($loan) + Money::toPaisa($charge);
        $each = Money::toPaisa($installment);
        if ($each <= 0) {
            throw new DomainException('কিস্তির পরিমাণ শূন্যের বেশি হতে হবে।');
        }
        if ($each > $total) {
            throw new DomainException('প্রতি কিস্তির টাকা মোট পরিশোধযোগ্য টাকার চেয়ে বেশি হতে পারবে না।');
        }
        $count = intdiv($total + $each - 1, $each);
        $last = $total - $each * ($count - 1);

        return ['total' => Money::fromPaisa($total), 'count' => $count, 'last' => Money::fromPaisa($last)];
    }

    public function open(Member $member, array $data, User $actor): Account
    {
        if (! $member->isActive()) {
            throw new DomainException('নিষ্ক্রিয় সদস্যকে ঋণ দেওয়া যাবে না।');
        }

        $loan = Money::fromPaisa(Money::toPaisa((string) $data['loan_amount']));
        $charge = Money::fromPaisa(Money::toPaisa((string) ($data['service_charge'] ?? '0')));
        $each = Money::fromPaisa(Money::toPaisa((string) $data['installment_amount']));
        $terms = self::terms($loan, $charge, $each);
        if ($terms['count'] > self::MAX_INSTALLMENTS) {
            throw new DomainException('মোট কিস্তির সংখ্যা '.self::MAX_INSTALLMENTS.'টির বেশি হতে পারবে না।');
        }
        $frequency = KistiFrequency::from($data['frequency']);
        /** @var CarbonInterface $start */
        $start = $data['start_date'];
        /** @var CarbonInterface $first */
        $first = $data['first_installment_date'];

        return DB::transaction(function () use ($member, $data, $actor, $loan, $charge, $each, $terms, $frequency, $start, $first) {
            $account = Account::query()->create([
                'account_no' => $this->sequences->next('kisti_account', 'KS', 5, false),
                'member_id' => $member->id,
                'type' => AccountType::Kisti,
                'assigned_collector_id' => $member->collector_id,
                'status' => AccountStatus::Active,
                'opened_at' => today(),
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
            ]);

            $account->kistiDetail()->create([
                'loan_amount' => $loan,
                'service_charge' => $charge,
                'total_payable' => $terms['total'],
                'frequency' => $frequency,
                'installment_amount' => $each,
                'total_installments' => $terms['count'],
                'last_installment_amount' => $terms['last'],
                'start_date' => $start->toDateString(),
                'first_installment_date' => $first->toDateString(),
            ]);

            $this->schedule->generate($account->load('kistiDetail'));

            $this->audit->log('kisti.opened', $account, [], [
                'member_id' => $member->id, 'loan_amount' => $loan, 'service_charge' => $charge,
                'total_payable' => $terms['total'], 'frequency' => $frequency->value,
                'installment_amount' => $each, 'total_installments' => $terms['count'],
                'start_date' => $start->toDateString(), 'first_installment_date' => $first->toDateString(),
            ], $actor->id);

            return $account->refresh();
        });
    }

    /** For a loan entered by mistake. Allowed only while no money has been received on it. */
    public function cancel(Account $account, string $reason, User $actor): void
    {
        DB::transaction(function () use ($account, $reason, $actor) {
            $account = Account::query()->lockForUpdate()->findOrFail($account->id);
            if ($account->status !== AccountStatus::Active) {
                throw new DomainException('শুধু চালু ঋণ বাতিল করা যায়।');
            }
            if (Installment::query()->where('account_id', $account->id)->where('amount_paid', '>', 0)->exists()) {
                throw new DomainException('এই ঋণে টাকা জমা হয়েছে, তাই বাতিল করা যাবে না। ভুল লেনদেন থাকলে লেনদেন বাতিল পদ্ধতি ব্যবহার করুন।');
            }

            $account->forceFill(['status' => AccountStatus::Cancelled, 'closed_at' => today()])->save();
            $this->audit->log('kisti.cancelled', $account, ['status' => 'active'], ['status' => 'cancelled', 'reason' => $reason], $actor->id);
        });
    }
}
