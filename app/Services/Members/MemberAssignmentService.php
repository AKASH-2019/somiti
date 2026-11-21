<?php

namespace App\Services\Members;

use App\Models\Account;
use App\Models\CollectorMemberAssignment;
use App\Models\Member;
use App\Models\User;
use App\Services\AuditService;
use DomainException;
use Illuminate\Support\Facades\DB;

class MemberAssignmentService
{
    public function __construct(private AuditService $audit) {}

    /** Closes the current assignment (if any) and opens a new one. No-op if already assigned to $collector. */
    public function assign(Member $member, User $collector, User $actor, ?string $reason = null): void
    {
        $this->ensureAssignable($collector);

        DB::transaction(function () use ($member, $collector, $actor, $reason) {
            $member = Member::query()->lockForUpdate()->findOrFail($member->id);
            $previous = $member->collector_id;

            if ($previous === $collector->id) {
                return;
            }

            $now = now();
            CollectorMemberAssignment::query()
                ->where('member_id', $member->id)->whereNull('assigned_to')
                ->update(['assigned_to' => $now, 'updated_at' => $now]);

            CollectorMemberAssignment::query()->create([
                'member_id' => $member->id,
                'collector_id' => $collector->id,
                'assigned_from' => $now,
                'assigned_by' => $actor->id,
                'reason' => $reason,
            ]);

            $member->forceFill(['collector_id' => $collector->id, 'updated_by' => $actor->id])->save();

            // A member's accounts are always handled by the member's current collector.
            Account::query()->where('member_id', $member->id)->update(['assigned_collector_id' => $collector->id]);

            $this->audit->log('member.assigned', $member,
                ['collector_id' => $previous],
                ['collector_id' => $collector->id, 'reason' => $reason],
                $actor->id);
        });
    }

    /** Moves every member of $from to $to (e.g. a collector leaves). Returns the number moved. */
    public function transferAll(User $from, User $to, User $actor, ?string $reason = null): int
    {
        if ($from->id === $to->id) {
            throw new DomainException('একই কালেক্টরের কাছে স্থানান্তর করা যাবে না।');
        }
        $this->ensureAssignable($to);

        return DB::transaction(function () use ($from, $to, $actor, $reason) {
            $count = 0;
            Member::query()->where('collector_id', $from->id)->orderBy('id')
                ->each(function (Member $member) use ($to, $actor, $reason, &$count) {
                    $this->assign($member, $to, $actor, $reason);
                    $count++;
                });

            $this->audit->log('collector.members_transferred', $from, [], [
                'to_collector_id' => $to->id, 'count' => $count, 'reason' => $reason,
            ], $actor->id);

            return $count;
        });
    }

    private function ensureAssignable(User $collector): void
    {
        if (! $collector->isCollector() || ! $collector->is_active) {
            throw new DomainException('শুধু সক্রিয় কালেক্টরকে দায়িত্ব দেওয়া যাবে।');
        }
    }
}
