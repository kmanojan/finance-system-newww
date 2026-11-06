<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DatabaseDocumentStorageTest extends TestCase
{
    use RefreshDatabase;

    public function test_private_documents_survive_adapter_recreation_and_support_download_operations(): void
    {
        $disk = Storage::disk('database-documents');
        $bytes = "%PDF-1.4\n".random_bytes(40);
        $disk->put('loans/one.pdf', $bytes);
        Storage::forgetDisk('database-documents');
        $disk = Storage::disk('database-documents');
        $this->assertSame($bytes, $disk->get('loans/one.pdf'));
        $this->assertSame(strlen($bytes), $disk->size('loans/one.pdf'));
        $this->assertSame('private', $disk->getVisibility('loans/one.pdf'));
        $this->assertSame($bytes, stream_get_contents($disk->readStream('loans/one.pdf')));
        $disk->copy('loans/one.pdf', 'loans/two.pdf');
        $disk->move('loans/two.pdf', 'receipts/two.pdf');
        $this->assertFalse($disk->exists('loans/two.pdf'));
        $this->assertTrue($disk->exists('receipts/two.pdf'));
        $this->assertEquals(['loans/one.pdf', 'receipts/two.pdf'], $disk->allFiles());
        $disk->deleteDirectory('loans');
        $this->assertFalse($disk->exists('loans/one.pdf'));
        $this->assertTrue($disk->exists('receipts/two.pdf'));
        $disk->delete('receipts/two.pdf');
        $this->assertDatabaseCount('private_document_files', 0);
    }

    public function test_document_writes_roll_back_with_the_financial_transaction(): void
    {
        DB::beginTransaction();
        Storage::disk('database-documents')->put('loan/test.pdf', 'test');
        DB::rollBack();
        $this->assertFalse(Storage::disk('database-documents')->exists('loan/test.pdf'));
    }
}
