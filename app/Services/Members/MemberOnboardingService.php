<?php

namespace App\Services\Members;

use App\Models\Account;
use App\Models\Member;
use App\Models\User;
use App\Services\Somiti\SomitiAccountService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** New member, optionally with their Somiti account, saved together (all or nothing). */
class MemberOnboardingService
{
    public function __construct(private MemberService $members, private SomitiAccountService $somiti) {}

    /** @return array{0: Member, 1: ?Account} */
    public function create(array $data, User $actor): array
    {
        return DB::transaction(function () use ($data, $actor) {
            $member = $this->members->create($data, $actor);
            $account = null;

            if (! empty($data['open_somiti'])) {
                $account = $this->somiti->open(
                    $member,
                    (int) $data['number_of_shares'],
                    Carbon::parse($data['somiti_start_date']),
                    $actor,
                    null,
                    (string) ($data['opening_balance'] ?? '0'),
                    $data['opening_balance_note'] ?? null,
                );
            }

            return [$member, $account];
        });
    }

    public static function message(Member $member, ?Account $account): string
    {
        $text = "সদস্য {$member->name} যোগ করা হয়েছে। সদস্য আইডি: {$member->member_code}।";
        if ($account) {
            $text .= " সমিতি অ্যাকাউন্ট {$account->account_no} খোলা হয়েছে, দৈনিক জমা ".bn_money($account->somitiDetail->daily_deposit_amount).'।';
        }

        return $text;
    }
}
