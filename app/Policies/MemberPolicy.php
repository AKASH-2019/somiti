<?php

namespace App\Policies;

use App\Models\Member;
use App\Models\User;

class MemberPolicy
{
    public function viewAny(User $user): bool
    {
        return true; // lists are always scoped with Member::visibleTo()
    }

    public function view(User $user, Member $member): bool
    {
        return $user->isAdmin() || $member->collector_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->is_active;
    }

    public function update(User $user, Member $member): bool
    {
        return $user->isAdmin() || $member->collector_id === $user->id;
    }

    public function assign(User $user, Member $member): bool
    {
        return $user->isAdmin();
    }

    public function changeStatus(User $user, Member $member): bool
    {
        return $user->isAdmin();
    }
}
