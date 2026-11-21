<?php

namespace Database\Factories;

use App\Enums\MemberStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\Member> */
class MemberFactory extends Factory
{
    public function definition(): array
    {
        return [
            'member_code' => 'M-'.fake()->unique()->numerify('#####'),
            'name' => fake()->name(),
            'business_name' => fake()->company(),
            'business_address' => fake()->streetAddress(),
            'mobile' => '017'.fake()->numerify('########'),
            'joined_at' => today()->subMonths(2),
            'status' => MemberStatus::Active,
            'collector_id' => User::factory(),
            'created_by' => fn (array $a) => $a['collector_id'],
        ];
    }
}
