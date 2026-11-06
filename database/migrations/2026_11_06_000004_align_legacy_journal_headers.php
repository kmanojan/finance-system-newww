<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Imported installations may predate the journal number and posting status.
        if (! Schema::hasColumn('journal_entries', 'entry_no')) {
            Schema::table('journal_entries', fn (Blueprint $table) => $table->string('entry_no', 50)->nullable()->unique());
        }
        if (! Schema::hasColumn('journal_entries', 'status')) {
            Schema::table('journal_entries', fn (Blueprint $table) => $table->string('status', 50)->default('posted'));
        }
    }

    public function down(): void
    {
        // Preserve journal identifiers and posting history on rollback.
    }
};
