<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('loans', 'party_id')) {
            DB::statement('ALTER TABLE loans ADD COLUMN party_id INT NULL');
        }
    }

    public function down(): void
    {
        //
    }
};
