<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\Member;
use App\Models\User;
use App\Services\Members\MemberAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CollectorManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    public function test_admin_creates_collector_who_can_then_log_in(): void
    {
        $this->actingAs($this->admin)->post(route('admin.collectors.store'), [
            'name' => 'রহিম',
            'username' => 'Rahim',
            'mobile' => '০১৭১১২২৩৩৪৪', // Bengali digits are accepted
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'opening_cash' => '500',
        ])->assertRedirect()->assertSessionHas('success');

        $rahim = User::query()->where('username', 'rahim')->firstOrFail();
        $this->assertTrue($rahim->isCollector());
        $this->assertSame('01711223344', $rahim->mobile);
        $this->assertSame('500.00', $rahim->opening_cash);
        $this->assertTrue(AuditLog::query()->where('action', 'collector.created')->exists());

        auth()->logout();
        $this->post(route('login.store'), ['username' => 'RAHIM', 'password' => 'secret123']);
        $this->assertAuthenticatedAs($rahim);
    }

    public function test_username_must_be_unique_and_mobile_valid(): void
    {
        User::factory()->create(['username' => 'karim']);

        $this->actingAs($this->admin)->post(route('admin.collectors.store'), [
            'name' => 'x', 'username' => 'karim', 'mobile' => '12345',
            'password' => 'secret123', 'password_confirmation' => 'secret123',
        ])->assertSessionHasErrors(['username', 'mobile']);
    }

    public function test_admin_resets_collector_password(): void
    {
        $collector = User::factory()->create();

        $this->actingAs($this->admin)
            ->put(route('admin.collectors.password', $collector), ['password' => 'newpass99', 'password_confirmation' => 'newpass99'])
            ->assertSessionHas('success');

        $this->assertTrue(Hash::check('newpass99', $collector->fresh()->password));
    }

    public function test_short_reset_password_goes_to_its_own_error_bag(): void
    {
        $collector = User::factory()->create();

        $this->actingAs($this->admin)
            ->put(route('admin.collectors.password', $collector), ['password' => 'short', 'password_confirmation' => 'short'])
            ->assertSessionHasErrorsIn('resetPassword', 'password');
    }

    public function test_deactivated_collector_cannot_log_in(): void
    {
        $collector = User::factory()->create(['username' => 'karim']);

        $this->actingAs($this->admin)->patch(route('admin.collectors.status', $collector));
        $this->assertFalse($collector->fresh()->is_active);

        auth()->logout();
        $this->post(route('login.store'), ['username' => 'karim', 'password' => 'password'])
            ->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_collector_routes_cannot_touch_an_admin_account(): void
    {
        $otherAdmin = User::factory()->admin()->create();

        $this->actingAs($this->admin)->get(route('admin.collectors.show', $otherAdmin))->assertNotFound();
        $this->actingAs($this->admin)->patch(route('admin.collectors.status', $otherAdmin))->assertNotFound();
    }

    public function test_collector_cannot_manage_collectors(): void
    {
        $collector = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($collector)->get(route('admin.collectors.index'))->assertForbidden();
        $this->actingAs($collector)->put(route('admin.collectors.password', $other), [
            'password' => 'hacked123', 'password_confirmation' => 'hacked123',
        ])->assertForbidden();
    }

    public function test_transfer_moves_all_members_and_records_history(): void
    {
        $rahim = User::factory()->create();
        $karim = User::factory()->create();
        $service = app(MemberAssignmentService::class);
        $members = Member::factory()->count(3)->create(['collector_id' => null, 'created_by' => $this->admin->id]);
        $members->each(fn ($m) => $service->assign($m, $rahim, $this->admin));

        $this->actingAs($this->admin)
            ->post(route('admin.collectors.transfer', $rahim), ['to_collector_id' => $karim->id, 'reason' => 'চাকরি ছেড়েছেন'])
            ->assertSessionHas('success');

        $this->assertSame(0, $rahim->members()->count());
        $this->assertSame(3, $karim->members()->count());
        foreach ($members as $m) {
            $this->assertSame(2, $m->assignments()->count());
            $this->assertSame(1, $m->assignments()->whereNull('assigned_to')->count());
        }
    }

    public function test_cannot_transfer_to_inactive_collector_or_to_self(): void
    {
        $rahim = User::factory()->create();
        $inactive = User::factory()->inactive()->create();

        $this->actingAs($this->admin)->post(route('admin.collectors.transfer', $rahim), ['to_collector_id' => $inactive->id])
            ->assertSessionHasErrorsIn('transfer', 'to_collector_id');
        $this->actingAs($this->admin)->post(route('admin.collectors.transfer', $rahim), ['to_collector_id' => $rahim->id])
            ->assertSessionHasErrorsIn('transfer', 'to_collector_id');
    }
}
