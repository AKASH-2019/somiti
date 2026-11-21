<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CollectorService
{
    public function __construct(private AuditService $audit, private \App\Services\Cash\CashLedgerService $ledger) {}

    public function create(array $data, User $actor): User
    {
        return DB::transaction(function () use ($data, $actor) {
            $collector = User::query()->create([
                'name' => $data['name'],
                'username' => $data['username'],
                'mobile' => $data['mobile'] ?? null,
                'password' => $data['password'],
                'opening_cash' => $data['opening_cash'] ?? 0,
                'role' => UserRole::Collector,
                'is_active' => true,
            ]);

            if (\App\Support\Money::toPaisa(number_format((float) $collector->opening_cash, 2, '.', '')) > 0) {
                $this->ledger->credit($collector, \App\Enums\LedgerType::Opening, number_format((float) $collector->opening_cash, 2, '.', ''),
                    null, 'প্রারম্ভিক ক্যাশ', $actor);
            }

            $this->audit->log('collector.created', $collector, [],
                $collector->only('name', 'username', 'mobile', 'opening_cash'), $actor->id);

            return $collector;
        });
    }

    /** Opening cash is fixed after creation; later changes go through cash adjustments (Phase 6). */
    public function update(User $collector, array $data, User $actor): User
    {
        $collector->fill(array_intersect_key($data, array_flip(['name', 'username', 'mobile'])));
        $dirty = $collector->getDirty();

        if ($dirty) {
            $old = array_intersect_key($collector->getOriginal(), $dirty);
            $collector->save();
            $this->audit->log('collector.updated', $collector, $old, $dirty, $actor->id);
        }

        return $collector;
    }

    public function resetPassword(User $collector, string $password, User $actor): void
    {
        $collector->forceFill(['password' => $password, 'remember_token' => null])->save();

        // Sign the collector out everywhere so the old password stops working immediately.
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $collector->id)->delete();
        }

        $this->audit->log('collector.password_reset', $collector, [], [], $actor->id);
    }

    public function setActive(User $collector, bool $active, User $actor): void
    {
        if ($collector->is_active === $active) {
            return;
        }
        $collector->forceFill(['is_active' => $active])->save();
        $this->audit->log($active ? 'collector.activated' : 'collector.deactivated', $collector, [], [], $actor->id);
    }
}
