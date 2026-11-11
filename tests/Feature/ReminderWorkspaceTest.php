<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReminderWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private function reminder(string $title, string $date, array $fields = []): int
    {
        return DB::table('reminders')->insertGetId($fields + ['title' => $title, 'due_date' => $date, 'type' => 'custom']);
    }

    public function test_list_priority_and_calendar_share_the_same_filtered_reminders(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 11)->startOfDay());
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
        $overdue = $this->reminder('Overdue review', '2026-11-08');
        $today = $this->reminder('Today review', '2026-11-11');
        $soon = $this->reminder('Upcoming review', '2026-11-17', ['notes' => 'Check receipt']);
        $this->reminder('Later review', '2026-11-20');
        $this->reminder('Completed review', '2026-11-07', ['status' => 'settled']);
        $this->reminder('Snoozed review', '2026-11-08', ['status' => 'snoozed', 'snoozed_until' => now()->addDay()]);
        $this->reminder('Resumed review', '2026-11-09', ['status' => 'snoozed', 'snoozed_until' => now()->subDay()]);

        $this->get('/reminders?type=custom')
            ->assertOk()
            ->assertViewHas('priority', ['overdue' => 2, 'today' => 1, 'week' => 1])
            ->assertSeeInOrder(['Overdue review', 'Today review', 'Upcoming review'])
            ->assertDontSee('Completed review')
            ->assertDontSee('Snoozed review');
        $this->get('/reminders?type=custom&when=week')
            ->assertOk()
            ->assertSee('Upcoming review')
            ->assertDontSee('Later review')
            ->assertDontSee('Overdue review');
        $this->get('/reminders?type=custom&view=calendar&month=2026-11&date=2026-11-17&search=receipt')
            ->assertOk()
            ->assertViewHas('selectedDate', '2026-11-17')
            ->assertSee('data-reminder-create="2026-11-17"', false)
            ->assertSee('action="/reminders/'.$soon.'/status"', false)
            ->assertDontSee('data-reminder-row="custom_'.$today.'"', false)
            ->assertDontSee('data-reminder-row="custom_'.$overdue.'"', false);
        $this->get('/reminders?type=custom&status=completed')
            ->assertOk()->assertSee('Completed review')->assertDontSee('days overdue');
        $this->get('/reminders?type=custom&status=snoozed')
            ->assertOk()->assertSee('Snoozed review')->assertDontSee('Resumed review');
    }

    public function test_custom_actions_use_numeric_ids_and_leave_linked_loan_reminders_intact(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
        $from = '/reminders?view=calendar&month=2026-11&date=2026-11-17';
        $this->from($from)->post('/reminders', ['title' => 'Check delivery', 'due_date' => '2026-11-17', 'notify_before_days' => 2])
            ->assertRedirect($from);
        $id = DB::table('reminders')->where('title', 'Check delivery')->value('id');
        $linked = $this->reminder('Loan maturity', '2026-11-17', ['reference_type' => 'Loan', 'reference_id' => 999]);

        $this->from($from)->post('/reminders/'.$id.'/status', ['status' => 'snoozed'])->assertRedirect($from);
        $this->assertDatabaseHas('reminders', ['id' => $id, 'status' => 'snoozed']);
        $this->post('/reminders/'.$id.'/status', ['status' => 'settled'])->assertRedirect();
        $this->assertDatabaseHas('reminders', ['id' => $id, 'status' => 'settled']);
        $this->post('/reminders/'.$id.'/status', ['status' => 'pending'])->assertRedirect();
        $this->assertDatabaseHas('reminders', ['id' => $id, 'status' => 'pending']);
        $this->delete('/reminders/'.$linked)->assertNotFound();
        $this->assertDatabaseHas('reminders', ['id' => $linked, 'status' => 'pending']);
        $this->delete('/reminders/'.$id)->assertRedirect();
        $this->assertDatabaseMissing('reminders', ['id' => $id]);
    }
}
