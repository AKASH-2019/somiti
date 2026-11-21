<?php

namespace Tests\Feature\Auth;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_is_in_bengali(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('লগইন করুন');
    }

    public function test_admin_logs_in_with_username_and_lands_on_admin_dashboard(): void
    {
        $admin = User::factory()->admin()->create(['username' => 'admin']);

        $this->post(route('login.store'), ['username' => 'admin', 'password' => 'password'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($admin);

        $this->get(route('dashboard'))->assertRedirect(route('admin.dashboard'));
        $this->get(route('admin.dashboard'))->assertOk()->assertSee('ড্যাশবোর্ড');
        $this->assertTrue(AuditLog::query()->where('action', 'auth.login')->exists());
    }

    public function test_collector_lands_on_collector_dashboard(): void
    {
        User::factory()->create(['username' => 'rahim']);

        $this->post(route('login.store'), ['username' => 'rahim', 'password' => 'password']);

        $this->get(route('dashboard'))->assertRedirect(route('collector.dashboard'));
        $this->get(route('collector.dashboard'))->assertOk();
    }

    public function test_wrong_password_shows_bengali_error(): void
    {
        User::factory()->create(['username' => 'rahim']);

        $this->from(route('login'))
            ->post(route('login.store'), ['username' => 'rahim', 'password' => 'wrong'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['username' => 'ইউজারনেম অথবা পাসওয়ার্ড সঠিক নয়।']);
        $this->assertGuest();
    }

    public function test_inactive_user_cannot_log_in(): void
    {
        User::factory()->inactive()->create(['username' => 'karim']);

        $this->post(route('login.store'), ['username' => 'karim', 'password' => 'password'])
            ->assertSessionHasErrors(['username' => __('auth.inactive')]);
        $this->assertGuest();
    }

    public function test_user_deactivated_mid_session_is_signed_out(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $user->update(['is_active' => false]);

        $this->get(route('collector.dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_login_is_throttled_after_five_failures(): void
    {
        User::factory()->create(['username' => 'rahim']);

        foreach (range(1, 5) as $_) {
            $this->post(route('login.store'), ['username' => 'rahim', 'password' => 'wrong']);
        }

        $this->post(route('login.store'), ['username' => 'rahim', 'password' => 'password'])
            ->assertSessionHasErrors('username');
        $this->assertGuest();
    }
}
