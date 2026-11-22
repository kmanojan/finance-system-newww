<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PartySearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_matches_party_details_and_phone_formats_before_pagination(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
        $id = DB::table('parties')->insertGetId([
            'name' => 'Sasi Supplies', 'types' => 'vendor', 'contact_person' => 'Anu Example',
            'email' => 'Orders_Desk@example.test', 'phone' => '+94 (77) 234-5678',
            'tax_id' => 'VAT-7788', 'address' => '34 Market Road',
        ]);
        DB::table('parties')->insert([
            'name' => 'Sasi Supplies', 'types' => 'client', 'email' => 'OrdersXDesk@example.test',
        ]);
        for ($i = 0; $i < 16; $i++) {
            DB::table('parties')->insert(['name' => 'Other '.$i, 'types' => 'vendor']);
        }

        foreach (['sasi', 'ANU EXAMPLE', 'orders_desk@', '0772345678', '077 234 5678', '234-5678', 'vat-7788', 'market road'] as $search) {
            $this->get('/master/parties?'.http_build_query(['type' => 'vendor', 'status' => 'active', 'search' => $search]))
                ->assertOk()
                ->assertViewHas('data', fn ($page) => $page->total() === 1 && $page->items()[0]->id === $id);
        }

        $this->get('/master/parties?type=all&search=sasi')->assertOk()->assertViewHas('data', fn ($page) => $page->total() === 2);
        $this->get('/master/parties?type=all&search=orders_desk%40')
            ->assertOk()->assertViewHas('data', fn ($page) => $page->total() === 1 && $page->items()[0]->id === $id);
        $this->get('/master/parties?search=%25')->assertOk()->assertViewHas('data', fn ($page) => $page->total() === 0);
    }
}
