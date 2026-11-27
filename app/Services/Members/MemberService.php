<?php

namespace App\Services\Members;

use App\Enums\MemberStatus;
use App\Models\Member;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;

class MemberService
{
    private const EDITABLE = ['name', 'business_name', 'business_address', 'mobile', 'joined_at', 'notes', 'member_code'];

    public function __construct(
        private MemberCodeService $codes,
        private MemberAssignmentService $assignments,
        private AuditService $audit,
    ) {}

    /**
     * A collector creating a member is always assigned that member.
     * An admin must choose the collector.
     */
    public function create(array $data, User $actor): Member
    {
        $collector = $actor->isCollector() ? $actor : User::query()->findOrFail($data['collector_id']);

        return DB::transaction(function () use ($data, $actor, $collector) {
            $member = Member::query()->create([
                ...array_intersect_key($data, array_flip(self::EDITABLE)),
                'member_code' => MemberCodeService::normalize($data['member_code'] ?? null) ?? $this->codes->next(),
                'status' => MemberStatus::Active,
                'created_by' => $actor->id,
            ]);

            $this->audit->log('member.created', $member, [], $member->only([...self::EDITABLE, 'member_code']), $actor->id);
            $this->assignments->assign($member, $collector, $actor, 'নতুন সদস্য');

            return $member->refresh();
        });
    }

    public function update(Member $member, array $data, User $actor): Member
    {
        $member->fill(array_intersect_key($data, array_flip(self::EDITABLE)));
        $dirty = $member->getDirty();

        if ($dirty) {
            $old = array_intersect_key($member->getOriginal(), $dirty);
            $member->updated_by = $actor->id;
            $member->save();
            $this->audit->log('member.updated', $member, $this->plain($old), $this->plain($dirty), $actor->id);
        }

        return $member;
    }

    public function setStatus(Member $member, MemberStatus $status, User $actor): void
    {
        if ($member->status === $status) {
            return;
        }
        $old = $member->status->value;
        $member->forceFill(['status' => $status, 'updated_by' => $actor->id])->save();
        $this->audit->log('member.status_changed', $member, ['status' => $old], ['status' => $status->value], $actor->id);
    }

    private function plain(array $values): array
    {
        return array_map(fn ($v) => $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : $v, $values);
    }
}
