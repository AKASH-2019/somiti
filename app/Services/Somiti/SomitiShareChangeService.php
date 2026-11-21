<?php

namespace App\Services\Somiti;

use App\Enums\InstallmentStatus;
use App\Models\Account;
use App\Models\Installment;
use App\Models\SomitiShareChange;
use App\Models\User;
use App\Services\AuditService;
use App\Support\BanglaFormatter;
use App\Support\Money;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Changes an account's share count from an effective date.
 *  - Days before the effective date are never touched ("previous as it is").
 *  - Days on/after it get the new daily amount. If they were prepaid at a lower rate, the difference becomes due.
 *  - A reduction is refused if a day on/after the effective date already holds more than the new amount.
 * The account keeps its original share value.
 */
class SomitiShareChangeService
{
    public function __construct(private AuditService $audit) {}

    public function change(Account $account, int $newShares, CarbonInterface $effective, User $actor, ?string $reason = null): SomitiShareChange
    {
        return DB::transaction(function () use ($account, $newShares, $effective, $actor, $reason) {
            $account = Account::query()->with('somitiDetail')->lockForUpdate()->findOrFail($account->id);
            $detail = $account->somitiDetail;
            $date = $effective->toDateString();

            if (! $account->isActive()) {
                throw new DomainException('বন্ধ অ্যাকাউন্টের শেয়ার পরিবর্তন করা যাবে না।');
            }
            if ($newShares < 1) {
                throw new DomainException('কমপক্ষে ১টি শেয়ার লাগবে।');
            }
            if ($newShares === $detail->number_of_shares) {
                throw new DomainException('নতুন শেয়ারের সংখ্যা বর্তমান সংখ্যার সমান।');
            }
            if ($effective->lt(today())) {
                throw new DomainException('পরিবর্তনের তারিখ আজ বা তার পরের হতে হবে। আগের দিনের হিসাব অপরিবর্তিত থাকবে।');
            }
            if ($effective->lt($detail->start_date)) {
                throw new DomainException('পরিবর্তনের তারিখ সঞ্চয় শুরুর তারিখের আগে হতে পারবে না।');
            }
            $lastChange = SomitiShareChange::query()->where('account_id', $account->id)->max('effective_date');
            if ($lastChange && $date <= $lastChange) {
                throw new DomainException('এই তারিখে বা পরে আগেই একটি শেয়ার পরিবর্তন নির্ধারিত আছে।');
            }

            $newDaily = Money::multiply((string) $detail->share_value, $newShares);

            $overpaid = Installment::query()->where('account_id', $account->id)
                ->whereDate('due_date', '>=', $date)
                ->where('amount_paid', '>', $newDaily)
                ->orderBy('due_date')->first();
            if ($overpaid) {
                throw new DomainException('অগ্রিম জমা থাকায় '.BanglaFormatter::date($overpaid->due_date)
                    .' থেকে শেয়ার কমানো যাবে না। শেষ অগ্রিম জমার পরের কোনো তারিখ বেছে নিন।');
            }

            $change = SomitiShareChange::query()->create([
                'account_id' => $account->id,
                'old_shares' => $detail->number_of_shares,
                'new_shares' => $newShares,
                'share_value' => $detail->share_value,
                'old_daily_amount' => $detail->daily_deposit_amount,
                'new_daily_amount' => $newDaily,
                'effective_date' => $date,
                'changed_by' => $actor->id,
                'reason' => $reason,
            ]);

            $old = ['shares' => $detail->number_of_shares, 'daily_deposit_amount' => (string) $detail->daily_deposit_amount];
            $detail->update(['number_of_shares' => $newShares, 'daily_deposit_amount' => $newDaily]);

            Installment::query()->where('account_id', $account->id)->whereDate('due_date', '>=', $date)
                ->lockForUpdate()->get()
                ->each(function (Installment $i) use ($newDaily) {
                    $i->update([
                        'amount_due' => $newDaily,
                        'status' => InstallmentStatus::for($newDaily, (string) $i->amount_paid),
                    ]);
                });

            $this->audit->log('somiti.shares_changed', $account, $old, [
                'shares' => $newShares, 'daily_deposit_amount' => $newDaily, 'effective_date' => $date, 'reason' => $reason,
            ], $actor->id);

            return $change;
        });
    }
}
