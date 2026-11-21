<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->firstOrCreate(
            ['username' => env('ADMIN_USERNAME', 'admin')],
            [
                'name' => env('ADMIN_NAME', 'এডমিন'),
                'role' => UserRole::Admin,
                'is_active' => true,
                'password' => env('ADMIN_PASSWORD', 'ChangeMe@2026'),
            ],
        );
    }
}
