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

    public function test_project_index_search_matches_linked_parties_within_existing_filters(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
        $company = DB::table('companies')->insertGetId(['name' => 'Test Company', 'base_currency' => 'LKR']);
        $department = DB::table('departments')->insertGetId(['name' => 'Delivery', 'company_id' => $company]);
        $partner = DB::table('parties')->insertGetId([
            'name' => 'Sasi Studio', 'types' => 'partner', 'email' => 'Team@Sasi.example', 'phone' => '+94 77 234-5678',
        ]);
        $target = DB::table('projects')->insertGetId(['name' => 'Harbor Project', 'company_id' => $company, 'department_id' => $department]);
        $completed = DB::table('projects')->insertGetId(['name' => 'Old project', 'company_id' => $company, 'department_id' => $department, 'status' => 'completed']);
        $outside = DB::table('projects')->insertGetId(['name' => 'Other project', 'company_id' => $company]);
        foreach ([$target, $completed, $outside] as $project) {
            DB::table('project_party')->insert(['project_id' => $project, 'party_id' => $partner, 'role' => 'partner']);
        }

        foreach (['harbor project', 'sasi', 'team@sasi.example', '0772345678'] as $search) {
            $this->get('/projects?'.http_build_query(['status' => 'active', 'department_id' => $department, 'search' => $search]))
                ->assertOk()
                ->assertViewHas('projects', fn ($page) => $page->total() === 1 && $page->items()[0]->id === $target);
        }
        $this->get('/projects?search=unknown')->assertOk()->assertSee('No projects match');
    }
}
