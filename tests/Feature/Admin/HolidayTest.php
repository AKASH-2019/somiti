<?php

namespace Tests\Feature\Admin;

use App\Models\Holiday;
use App\Models\User;
use App\Services\CalendarService;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class HolidayTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_adds_holiday_and_it_becomes_a_non_working_day(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $date = today()->addDays(10);

        $this->post(route('admin.holidays.store'), ['date' => $date->toDateString(), 'title' => 'বিজয় দিবস'])
            ->assertSessionHas('success');

        $this->assertFalse(app(CalendarService::class)->isWorkingDay($date));
    }

    public function test_duplicate_holiday_date_is_rejected(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Holiday::query()->create(['date' => '2026-12-16', 'title' => 'বিজয় দিবস']);

        $this->post(route('admin.holidays.store'), ['date' => '2026-12-16', 'title' => 'আবার'])
            ->assertSessionHasErrors('date');
    }

    public function test_past_holiday_cannot_be_deleted_but_future_one_can(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $past = Holiday::query()->create(['date' => today()->subDay(), 'title' => 'অতীত']);
        $future = Holiday::query()->create(['date' => today()->addDay(), 'title' => 'ভবিষ্যৎ']);

        $this->delete(route('admin.holidays.destroy', $past))->assertSessionHas('error');
        $this->delete(route('admin.holidays.destroy', $future))->assertSessionHas('success');

        $this->assertModelExists($past);
        $this->assertModelMissing($future);
    }

    public function test_weekly_off_day_is_not_a_working_day(): void
    {
        app(SettingService::class)->set('weekly_off_days', [5]); // Friday
        $friday = Carbon::parse('2026-11-20');

        $this->assertFalse(app(CalendarService::class)->isWorkingDay($friday));
        $this->assertTrue(app(CalendarService::class)->isWorkingDay($friday->copy()->addDay()));
    }
}
