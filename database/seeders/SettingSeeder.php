<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use App\Services\SettingService;
use Illuminate\Database\Seeder;

/** Inserts missing defaults only — never overwrites values the admin has changed. */
class SettingSeeder extends Seeder
{
    public function run(): void
    {
        foreach (SettingService::DEFAULTS as $key => $default) {
            SystemSetting::query()->firstOrCreate(['key' => $key], $default);
        }

        app(SettingService::class)->flush();
    }
}
