<?php

namespace Tests\Feature;

use App\Enums\MemberStatus;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberManagementTest extends TestCase
{
    use RefreshDatabase;

    private function memberData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'আব্দুল করিম',
            'business_name' => 'করিম স্টোর',
            'business_address' => 'বাজার রোড',
            'mobile' => '01812345678',
            'joined_at' => today()->toDateString(),
        ], $overrides);
    }

    public function test_admin_creates_member_with_generated_code_and_assignment(): void
    {
        $admin = User::factory()->admin()->create();
        $rahim = User::factory()->create();

        $this->actingAs($admin)->post(route('admin.members.store'), $this->memberData(['collector_id' => $rahim->id]))
            ->assertRedirect()->assertSessionHas('success');

        $member = Member::query()->firstOrFail();
        $this->assertSame('S5-001', $member->member_code);
        $this->assertSame($rahim->id, $member->collector_id);
        $this->assertSame(1, $member->assignments()->whereNull('assigned_to')->where('collector_id', $rahim->id)->count());
    }

    public function test_admin_must_choose_an_active_collector(): void
    {
        $admin = User::factory()->admin()->create();
        $inactive = User::factory()->inactive()->create();

        $this->actingAs($admin)->post(route('admin.members.store'), $this->memberData())
            ->assertSessionHasErrors('collector_id');
        $this->actingAs($admin)->post(route('admin.members.store'), $this->memberData(['collector_id' => $inactive->id]))
            ->assertSessionHasErrors('collector_id');
    }

    public function test_collector_creates_member_assigned_to_self_even_if_another_collector_is_posted(): void
    {
        $rahim = User::factory()->create();
        $karim = User::factory()->create();

        $this->actingAs($rahim)->post(route('collector.members.store'), $this->memberData(['collector_id' => $karim->id]))
            ->assertRedirect();

        $this->assertSame($rahim->id, Member::query()->firstOrFail()->collector_id);
    }

    public function test_collector_sees_and_opens_only_own_members(): void
    {
        $rahim = User::factory()->create();
        $karim = User::factory()->create();
        $mine = Member::factory()->create(['collector_id' => $rahim->id, 'name' => 'আমার সদস্য']);
        $theirs = Member::factory()->create(['collector_id' => $karim->id, 'name' => 'অন্যের সদস্য']);

        $this->actingAs($rahim)->get(route('collector.members.index'))
            ->assertOk()->assertSee('আমার সদস্য')->assertDontSee('অন্যের সদস্য');

        $this->actingAs($rahim)->get(route('collector.members.show', $mine))->assertOk();
        $this->actingAs($rahim)->get(route('collector.members.show', $theirs))->assertForbidden();
        $this->actingAs($rahim)->put(route('collector.members.update', $theirs), $this->memberData())->assertForbidden();
    }

    public function test_collector_cannot_reassign_or_change_status(): void
    {
        $rahim = User::factory()->create();
        $karim = User::factory()->create();
        $member = Member::factory()->create(['collector_id' => $rahim->id]);

        $this->actingAs($rahim)->post(route('admin.members.assign', $member), ['collector_id' => $karim->id])->assertForbidden();
        $this->actingAs($rahim)->patch(route('admin.members.status', $member))->assertForbidden();
    }

    public function test_search_accepts_bengali_digits(): void
    {
        $admin = User::factory()->admin()->create();
        Member::factory()->create(['mobile' => '01999888777', 'name' => 'খুঁজে পাওয়া']);
        Member::factory()->create(['mobile' => '01511111111', 'name' => 'অন্য কেউ']);

        $this->actingAs($admin)->get(route('admin.members.index', ['q' => '৮৮৮৭৭৭']))
            ->assertSee('খুঁজে পাওয়া')->assertDontSee('অন্য কেউ');
    }

    public function test_admin_reassigns_member_and_history_is_kept(): void
    {
        $admin = User::factory()->admin()->create();
        $rahim = User::factory()->create();
        $karim = User::factory()->create();
        $this->actingAs($admin)->post(route('admin.members.store'), $this->memberData(['collector_id' => $rahim->id]));
        $member = Member::query()->firstOrFail();

        $this->actingAs($admin)->post(route('admin.members.assign', $member), ['collector_id' => $karim->id, 'reason' => 'এলাকা পরিবর্তন'])
            ->assertSessionHas('success');

        $member->refresh();
        $this->assertSame($karim->id, $member->collector_id);
        $this->assertSame(2, $member->assignments()->count());
        $this->assertNotNull($member->assignments()->where('collector_id', $rahim->id)->first()->assigned_to);
        $this->actingAs($admin)->get(route('admin.members.show', $member))->assertSee('এলাকা পরিবর্তন');
    }

    public function test_admin_toggles_member_status(): void
    {
        $admin = User::factory()->admin()->create();
        $member = Member::factory()->create();

        $this->actingAs($admin)->patch(route('admin.members.status', $member));
        $this->assertSame(MemberStatus::Inactive, $member->fresh()->status);
    }

    public function test_invalid_mobile_and_future_join_date_rejected_in_bengali(): void
    {
        $rahim = User::factory()->create();

        $this->actingAs($rahim)->post(route('collector.members.store'), $this->memberData([
            'mobile' => '0171', 'joined_at' => today()->addDay()->toDateString(),
        ]))->assertSessionHasErrors([
            'mobile' => 'সঠিক মোবাইল নম্বর দিন (যেমন: ০১৭১১২২৩৩৪৪)।',
            'joined_at' => 'যোগদানের তারিখ আজকের পরে হতে পারবে না।',
        ]);
    }

    public function test_members_have_no_delete_route(): void
    {
        $this->assertFalse(app('router')->has('admin.members.destroy'));
        $this->assertFalse(app('router')->has('admin.collectors.destroy'));
    }

    public function test_mobile_with_country_code_spaces_and_dashes_is_cleaned(): void
    {
        $rahim = User::factory()->create();

        $this->actingAs($rahim)->post(route('collector.members.store'), $this->memberData(['mobile' => '+৮৮০ ১৭১১-২২৩৩৪৪']))
            ->assertSessionHas('success');

        $this->assertSame('01711223344', Member::query()->firstOrFail()->mobile);
    }
}
