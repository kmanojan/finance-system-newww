<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LoanAttachmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['filesystems.documents_disk' => 'local']);
        Storage::fake('local');
        Storage::fake('public');
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
    }

    private function terms(): array
    {
        return ['lender_name' => 'Attachment test lender', 'principal_amount' => 10000, 'currency' => 'LKR', 'claimed_date' => '2026-11-01', 'term_months' => 4, 'interest_method' => 'no_interest', 'frequency' => 'monthly'];
    }

    private function files(): array
    {
        return [UploadedFile::fake()->create('agreement.pdf', 20, 'application/pdf'), UploadedFile::fake()->image('receipt.png')];
    }

    private function createLoan(): int
    {
        $this->post('/loans', $this->terms() + ['attachments' => $this->files()])->assertSessionHasNoErrors()->assertRedirect();

        return DB::table('loans')->max('id');
    }

    public function test_create_and_pending_edit_append_multiple_downloadable_files(): void
    {
        $id = $this->createLoan();
        $original = DB::table('attachments')->pluck('file_path')->all();
        $this->assertCount(2, $original);
        $this->put("/loans/{$id}", $this->terms() + ['attachments' => $this->files()])->assertSessionHasNoErrors()->assertRedirect();
        $attachments = DB::table('attachments')->where('model_type', 'Loan')->where('model_id', $id)->get();
        $this->assertCount(4, $attachments);
        $this->assertCount(4, $attachments->pluck('file_path')->unique());
        foreach ($attachments as $attachment) {
            Storage::disk('local')->assertExists($attachment->file_path);
            $this->get("/attachments/{$attachment->id}/download")->assertOk()->assertDownload($attachment->file_name);
        }
        $this->get("/loans/{$id}")->assertOk()->assertSee('agreement.pdf')->assertSee('receipt.png');
    }

    public function test_activated_edit_appends_files_without_changing_financial_terms(): void
    {
        $id = $this->createLoan();
        DB::table('loans')->where('id', $id)->update(['status' => 'active']);
        $this->put("/loans/{$id}", ['purpose' => 'Updated description', 'attachments' => $this->files()])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseCount('attachments', 4);
        $this->assertDatabaseHas('loans', ['id' => $id, 'principal_amount' => 10000, 'status' => 'active', 'purpose' => 'Updated description']);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_invalid_files_reject_the_entire_batch_in_create_and_both_edit_states(): void
    {
        $badFiles = fn () => [UploadedFile::fake()->create('valid.pdf', 20, 'application/pdf'), UploadedFile::fake()->create('bad.exe', 20, 'application/octet-stream')];
        $this->post('/loans', $this->terms() + ['attachments' => $badFiles()])->assertSessionHasErrors('attachments.1');
        $this->assertDatabaseCount('loans', 0);
        $id = $this->createLoan();
        foreach (['pending', 'active'] as $status) {
            DB::table('loans')->where('id', $id)->update(['status' => $status]);
            $this->put("/loans/{$id}", $this->terms() + ['attachments' => $badFiles()])->assertSessionHasErrors('attachments.1');
            $this->put("/loans/{$id}", $this->terms() + ['attachments' => [UploadedFile::fake()->create('too-large.pdf', 10241, 'application/pdf')]])->assertSessionHasErrors('attachments.0');
            $this->assertDatabaseCount('attachments', 2);
            $this->assertCount(2, Storage::disk('local')->allFiles());
        }
    }

    public function test_storage_failure_rolls_back_the_batch_and_cleans_up_written_files(): void
    {
        $disk = \Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('putFile')->once()->ordered()->andReturn('attachments/loans/first.pdf');
        $disk->shouldReceive('putFile')->once()->ordered()->andReturn(false);
        $disk->shouldReceive('delete')->once()->with(['attachments/loans/first.pdf'])->andReturn(true);
        Storage::shouldReceive('disk')->with('local')->andReturn($disk);
        $this->post('/loans', $this->terms() + ['attachments' => $this->files()])->assertStatus(503);
        $this->assertDatabaseCount('loans', 0);
        $this->assertDatabaseCount('attachments', 0);
    }

    public function test_guest_cannot_download_private_attachments(): void
    {
        $this->createLoan();
        $id = DB::table('attachments')->value('id');
        auth()->logout();
        $this->get("/attachments/{$id}/download")->assertRedirect('/login');
    }

    public function test_pending_loan_deletion_removes_private_files_and_legacy_downloads_work(): void
    {
        $id = $this->createLoan();
        $paths = DB::table('attachments')->pluck('file_path')->all();
        Storage::disk('public')->put('attachments/loans/legacy.pdf', '%PDF legacy');
        $legacy = DB::table('attachments')->insertGetId(['model_type' => 'Loan', 'model_id' => $id, 'file_name' => 'legacy.pdf', 'file_path' => 'attachments/loans/legacy.pdf']);
        $this->get("/attachments/{$legacy}/download")->assertOk()->assertDownload('legacy.pdf');
        $this->delete("/loans/{$id}")->assertRedirect();
        $this->assertDatabaseCount('attachments', 0);
        foreach ($paths as $path) {
            Storage::disk('local')->assertMissing($path);
        }
        Storage::disk('public')->assertMissing('attachments/loans/legacy.pdf');
    }
}
