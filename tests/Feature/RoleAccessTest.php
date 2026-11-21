<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_collector_cannot_open_admin_pages(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.dashboard'))->assertForbidden();
        $this->get(route('admin.settings.edit'))->assertForbidden();
        $this->put(route('admin.settings.update'), ['org_name' => 'x', 'share_value' => 50])->assertForbidden();
        $this->get(route('admin.holidays.index'))->assertForbidden();
    }

    public function test_admin_cannot_open_collector_dashboard(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->get(route('collector.dashboard'))->assertForbidden();
    }

    public function test_admin_sidebar_shows_bengali_menu(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->get(route('admin.dashboard'))
            ->assertSee('সদস্য ব্যবস্থাপনা')
            ->assertSee('টাকা হস্তান্তর')
            ->assertSee('সেটিংস');
    }
}
