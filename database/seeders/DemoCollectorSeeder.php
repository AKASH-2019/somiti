<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

/** Local only: the two collectors from the handover example. Password: password */
class DemoCollectorSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([['rahim', 'রহিম', '01711000001'], ['karim', 'করিম', '01711000002']] as [$username, $name, $mobile]) {
            User::query()->firstOrCreate(['username' => $username], [
                'name' => $name, 'mobile' => $mobile, 'role' => UserRole::Collector,
                'is_active' => true, 'password' => 'password',
            ]);
        }
    }
}
