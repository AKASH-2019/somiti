<?php

namespace App\Policies;

use App\Models\Transaction;
use App\Models\User;

class TransactionPolicy
{
    public function view(User $user, Transaction $tx): bool
    {
        return $user->isAdmin()
            || $tx->collector_id === $user->id
            || $tx->account?->assigned_collector_id === $user->id;
    }

    public function reverse(User $user, Transaction $tx): bool
    {
        return $user->isAdmin() && ! $tx->isReversed();
    }
}
