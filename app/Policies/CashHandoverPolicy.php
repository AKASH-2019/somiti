<?php

namespace App\Policies;

use App\Enums\HandoverStatus;
use App\Models\CashHandover;
use App\Models\User;

class CashHandoverPolicy
{
    public function view(User $user, CashHandover $h): bool
    {
        return $user->isAdmin() || in_array($user->id, [$h->sender_collector_id, $h->receiver_collector_id], true);
    }

    public function confirm(User $user, CashHandover $h): bool
    {
        return $h->status === HandoverStatus::Pending && $user->id === $h->receiver_collector_id;
    }

    public function reject(User $user, CashHandover $h): bool
    {
        return $this->confirm($user, $h);
    }

    public function cancel(User $user, CashHandover $h): bool
    {
        return $h->status === HandoverStatus::Pending && $user->id === $h->sender_collector_id;
    }

    public function dispute(User $user, CashHandover $h): bool
    {
        return in_array($h->status, [HandoverStatus::Pending, HandoverStatus::Rejected], true)
            && in_array($user->id, [$h->sender_collector_id, $h->receiver_collector_id], true);
    }

    public function resolve(User $user, CashHandover $h): bool
    {
        return $user->isAdmin() && $h->status === HandoverStatus::Disputed;
    }
}
