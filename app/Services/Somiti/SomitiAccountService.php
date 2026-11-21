<?php

namespace App\Services\Somiti;

use App\Enums\AccountStatus;
use App\Enums\AccountType;
use App\Models\Account;
use App\Models\Member;
use App\Models\SomitiShareChange;
use App\Models\User;
use App\Services\AuditService;
use App\Services\NumberSequenceService;
use App\Services\SettingService;
use App\Support\Money;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Facades\DB;

class SomitiAccountService
{
    public function __construct(
        private SettingService $settings,
        private NumberSequenceService $sequences,
        private SomitiScheduleService $schedule,
        private AuditService $audit,
    ) {}

    public function open(
        Member $member,
        int $shares,
        CarbonInterface $startDate,
        User $actor,
        ?string $notes = null,
        string $openingBalance = '0',
        ?string $openingNote = null,
    ): Account
    {
        if (! $member->isActive()) {
            throw new DomainException('নিষ্ক্রিয় সদস্যের জন্য অ্যাকাউন্ট খোলা যাবে না।');
        }
        if ($shares < 1) {
            throw new DomainException('কমপক্ষে ১টি শেয়ার লাগবে।');
        }
        $openingBalance = Money::fromPaisa(Money::toPaisa($openingBalance === '' ? '0' : $openingBalance));
        if (Money::toPaisa($openingBalance) < 0) {
            throw new DomainException('প্রারম্ভিক সঞ্চয় ঋণাত্মক হতে পারবে না।');
        }

        $account = DB::transaction(function () use ($member, $shares, $startDate, $actor, $notes, $openingBalance, $openingNote) {
            $shareValue = $this->settings->shareValue();
            $daily = Money::multiply($shareValue, $shares);

            $account = Account::query()->create([
                'account_no' => $this->sequences->next('somiti_account', 'SM', 5, false),
                'member_id' => $member->id,
                'type' => AccountType::Somiti,
                'assigned_collector_id' => $member->collector_id,
                'status' => AccountStatus::Active,
                'opened_at' => today(),
                'notes' => $notes,
                'created_by' => $actor->id,
            ]);

            $account->somitiDetail()->create([
                'number_of_shares' => $shares,
                'share_value' => $shareValue,
                'daily_deposit_amount' => $daily,
                'start_date' => $startDate->toDateString(),
                'opening_balance' => $openingBalance,
                'opening_balance_note' => Money::toPaisa($openingBalance) > 0 ? $openingNote : null,
                'opening_balance_by' => Money::toPaisa($openingBalance) > 0 ? $actor->id : null,
            ]);

            SomitiShareChange::query()->create([
                'account_id' => $account->id,
                'old_shares' => null,
                'new_shares' => $shares,
                'share_value' => $shareValue,
                'old_daily_amount' => null,
                'new_daily_amount' => $daily,
                'effective_date' => $startDate->toDateString(),
                'changed_by' => $actor->id,
                'reason' => 'অ্যাকাউন্ট খোলা',
            ]);

            $this->audit->log('somiti.opened', $account, [], [
                'member_id' => $member->id, 'shares' => $shares, 'share_value' => $shareValue,
                'daily_deposit_amount' => $daily, 'start_date' => $startDate->toDateString(),
                'opening_balance' => $openingBalance,
            ], $actor->id);

            return $account;
        });

        $this->schedule->ensureThrough($account, today());

        return $account->refresh();
    }

    /** Admin-only correction of a wrongly entered opening balance. Always audited with a reason. */
    public function correctOpeningBalance(Account $account, string $amount, string $reason, User $actor): void
    {
        $amount = Money::fromPaisa(Money::toPaisa($amount));
        if (Money::toPaisa($amount) < 0) {
            throw new DomainException('প্রারম্ভিক সঞ্চয় ঋণাত্মক হতে পারবে না।');
        }

        DB::transaction(function () use ($account, $amount, $reason, $actor) {
            $detail = $account->somitiDetail()->lockForUpdate()->firstOrFail();
            $old = (string) $detail->opening_balance;
            if (Money::compare($old, $amount) === 0) {
                return;
            }

            $detail->update([
                'opening_balance' => $amount,
                'opening_balance_note' => $reason,
                'opening_balance_by' => $actor->id,
            ]);

            $this->audit->log('somiti.opening_balance_corrected', $account,
                ['opening_balance' => $old], ['opening_balance' => $amount, 'reason' => $reason], $actor->id);
        });
    }
}
