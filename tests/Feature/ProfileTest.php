<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Department;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_profile_general_info(): void
    {
        $user = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'role' => 'manager',
            'phone' => '0771122334',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get('/profile?tab=general');
        $response->assertStatus(200);
        $response->assertSee('John Doe');
        $response->assertSee('john@example.com');
        $response->assertSee('Personal Information');
        $response->assertSee('Change Password');
    }

    public function test_user_can_update_general_info(): void
    {
        $user = User::factory()->create([
            'name' => 'Initial Name',
            'email' => 'initial@example.com',
            'phone' => '0770000000',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->put(route('profile.general.update'), [
            'name' => 'Updated Profile Name',
            'email' => 'updated@example.com',
            'phone' => '0771112222',
        ]);

        $response->assertRedirect('/profile?tab=general');
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Profile Name',
            'email' => 'updated@example.com',
            'phone' => '0771112222',
        ]);
    }

    public function test_user_can_change_password_with_valid_current_password(): void
    {
        $user = User::factory()->create([
            'email' => 'password_test@example.com',
            'password' => Hash::make('old_secret_password'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->put(route('profile.password.update'), [
            'current_password' => 'old_secret_password',
            'password' => 'new_secret_password_123',
            'password_confirmation' => 'new_secret_password_123',
        ]);

        $response->assertRedirect('/profile?tab=general');
        $response->assertSessionHas('success');

        $this->assertTrue(Hash::check('new_secret_password_123', $user->fresh()->password));
    }

    public function test_user_cannot_change_password_with_wrong_current_password(): void
    {
        $user = User::factory()->create([
            'email' => 'wrong_pw_test@example.com',
            'password' => Hash::make('real_password_123'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->put(route('profile.password.update'), [
            'current_password' => 'incorrect_password',
            'password' => 'brand_new_password_123',
            'password_confirmation' => 'brand_new_password_123',
        ]);

        $response->assertSessionHasErrors('current_password');
        $this->assertTrue(Hash::check('real_password_123', $user->fresh()->password));
    }
}
