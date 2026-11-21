<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_changes_own_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('password.change'), [
            'current_password' => 'password',
            'password' => 'newsecret1',
            'password_confirmation' => 'newsecret1',
        ])->assertSessionHas('success');

        $this->assertTrue(Hash::check('newsecret1', $user->fresh()->password));
    }

    public function test_wrong_current_password_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('password.change'), [
            'current_password' => 'wrong',
            'password' => 'newsecret1',
            'password_confirmation' => 'newsecret1',
        ])->assertSessionHasErrors('current_password');
    }
}
