<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', fn (Blueprint $table) => $table->unsignedBigInteger('external_id')->nullable()->change());
    }

    public function down(): void
    {
        if (DB::table('employees')->whereNull('external_id')->exists()) {
            throw new RuntimeException('Manual employees exist. Preserve their records before reverting this migration.');
        }
        Schema::table('employees', fn (Blueprint $table) => $table->unsignedBigInteger('external_id')->nullable(false)->change());
    }
};
