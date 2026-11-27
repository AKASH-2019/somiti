<?php

namespace App\Policies;

use App\Models\Account;
use App\Models\Member;
use App\Models\User;

class AccountPolicy
{
    public function view(User $user, Account $account): bool
    {
        return $user->isAdmin() || $account->assigned_collector_id === $user->id;
    }

    /** Open an account for this member: admin for anyone, collector for own members. */
    public function create(User $user, Member $member): bool
    {
        return $member->isActive() && ($user->isAdmin() || $member->collector_id === $user->id);
    }

    /** Take money on this account: admin for any active account, collector for accounts assigned to them. */
    public function collect(User $user, Account $account): bool
    {
        return $account->isActive() && ($user->isAdmin() || $account->assigned_collector_id === $user->id);
    }

    public function changeShares(User $user, Account $account): bool
    {
        return $user->isAdmin();
    }

    public function settle(User $user, Account $account): bool
    {
        return $user->isAdmin() && $account->isActive();
    }

    public function cancel(User $user, Account $account): bool
    {
        return $user->isAdmin();
    }

    public function correctOpeningBalance(User $user, Account $account): bool
    {
        return $user->isAdmin();
    }
}
