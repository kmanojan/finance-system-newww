<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Department;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserMasterTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_users_master_page(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@example.com',
            'role' => 'admin',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('masters.users.index'));
        $response->assertStatus(200);
        $response->assertSee('Users');
        $response->assertSee('Add New User');
    }

    public function test_can_create_new_user(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@example.com',
            'role' => 'admin',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('masters.users.store'), [
            'name' => 'Test Staff',
            'email' => 'staff_unique_test@example.com',
            'password' => 'secret_password_123',
            'password_confirmation' => 'secret_password_123',
            'role' => 'staff',
            'phone' => '0771234567',
            'is_active' => '1',
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('users', [
            'email' => 'staff_unique_test@example.com',
            'role' => 'staff',
            'is_active' => 1,
        ]);
    }

    public function test_can_update_user(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@example.com',
            'role' => 'admin',
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'email' => 'staff_to_edit@example.com',
            'role' => 'staff',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->put(route('masters.users.update', $user->id), [
            'name' => 'Updated Name',
            'email' => 'staff_to_edit@example.com',
            'role' => 'manager',
            'phone' => '0779998888',
            'is_active' => '1',
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
            'role' => 'manager',
        ]);
    }

    public function test_can_toggle_user_status(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@example.com',
            'role' => 'admin',
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'email' => 'staff_to_toggle@example.com',
            'role' => 'staff',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->patch(route('masters.users.toggle_status', $user->id));
        $response->assertSessionHas('success');
        $this->assertFalse((bool)$user->fresh()->is_active);

        // Toggle back to active
        $response = $this->actingAs($admin)->patch(route('masters.users.toggle_status', $user->id));
        $response->assertSessionHas('success');
        $this->assertTrue((bool)$user->fresh()->is_active);
    }

    public function test_can_soft_delete_user(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@example.com',
            'role' => 'admin',
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'email' => 'staff_to_delete@example.com',
            'role' => 'staff',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->delete(route('masters.users.destroy', $user->id));
        $response->assertSessionHas('success');
        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }

    public function test_inactive_user_cannot_login(): void
    {
        User::factory()->create([
            'email' => 'inactive_user@example.com',
            'password' => Hash::make('password123'),
            'role' => 'staff',
            'is_active' => false,
        ]);

        $response = $this->post('/login', [
            'email' => 'inactive_user@example.com',
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_cannot_delete_self(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@example.com',
            'role' => 'admin',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->delete(route('masters.users.destroy', $admin->id));
        $response->assertSessionHas('error', 'You cannot delete your own account.');
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_cannot_deactivate_self(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@example.com',
            'role' => 'admin',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->patch(route('masters.users.toggle_status', $admin->id));
        $response->assertSessionHas('error', 'You cannot change the status of your own account.');
        $this->assertTrue((bool)$admin->fresh()->is_active);
    }

    public function test_filter_and_search_users(): void
    {
        $admin = User::factory()->create([
            'name' => 'Current Admin',
            'email' => 'admin@example.com',
            'role' => 'admin',
            'is_active' => true,
        ]);

        $user1 = User::factory()->create([
            'name' => 'Charlie Chaplin',
            'email' => 'charlie@example.com',
            'role' => 'accountant',
            'is_active' => true,
        ]);

        $user2 = User::factory()->create([
            'name' => 'Bob Builder',
            'email' => 'bob@example.com',
            'role' => 'staff',
            'is_active' => false,
        ]);

        // Search by name
        $response = $this->actingAs($admin)->get(route('masters.users.index', ['search' => 'Bob']));
        $response->assertSee('Bob Builder');
        $response->assertDontSee('Charlie Chaplin');

        // Filter by role
        $response = $this->actingAs($admin)->get(route('masters.users.index', ['role' => 'accountant']));
        $response->assertSee('Charlie Chaplin');
        $response->assertDontSee('Bob Builder');

        // Filter by status
        $response = $this->actingAs($admin)->get(route('masters.users.index', ['status' => 'inactive']));
        $response->assertSee('Bob Builder');
        $response->assertDontSee('Charlie Chaplin');
    }
}
