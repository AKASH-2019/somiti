<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\SettingService;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingSeeder::class);
    }

    public function test_share_value_defaults_to_twenty(): void
    {
        $this->assertSame('20.00', app(SettingService::class)->shareValue());
    }

    public function test_admin_can_change_share_value_and_off_days_and_it_is_audited(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->put(route('admin.settings.update'), [
            'org_name' => 'সততা সমিতি',
            'share_value' => '25',
            'weekly_off_days' => ['5'],
        ])->assertRedirect()->assertSessionHas('success');

        $settings = app(SettingService::class);
        $this->assertSame('25.00', $settings->shareValue());
        $this->assertSame([5], $settings->weeklyOffDays());
        $this->assertSame('সততা সমিতি', $settings->get('org_name'));

        $log = AuditLog::query()->where('action', 'settings.updated')->firstOrFail();
        $this->assertSame('20.00', $log->old_values['share_value']);
        $this->assertSame('25.00', $log->new_values['share_value']);
    }

    public function test_share_value_must_be_positive(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->put(route('admin.settings.update'), ['org_name' => 'x', 'share_value' => '0'])
            ->assertSessionHasErrors('share_value');
    }
}
