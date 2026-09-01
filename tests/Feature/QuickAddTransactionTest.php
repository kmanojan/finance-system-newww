<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class QuickAddTransactionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('companies')->insert([
            'name' => 'Apptimus',
            'base_currency' => 'LKR',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('departments')->insert([
            'name' => 'General / Admin',
            'company_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('categories')->insert([
            'name' => 'Office Expense',
            'type' => 'expense',
            'company_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_quick_add_transaction_via_json(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $cat = DB::table('categories')->first();

        $response = $this->actingAs($user)->postJson('/transactions', [
            'type' => 'expense',
            'amount' => 3500.50,
            'currency' => 'LKR',
            'category_id' => $cat->id,
            'transaction_date' => '2026-09-01',
            'payment_method' => 'Petty Cash',
            'description' => 'Office coffee supplies',
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Transaction created successfully!',
                 ]);

        $this->assertDatabaseHas('transactions', [
            'description' => 'Office coffee supplies',
            'amount' => 3500.50,
            'type' => 'expense',
            'payment_method' => 'Petty Cash',
        ]);
    }
}
