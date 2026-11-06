<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            // Existing facilities and new loans use type 1 unless explicitly changed.
            $table->string('loan_type', 32)->default('interest_separate');
        });
    }

    public function down(): void
    {
        Schema::table('loans', fn (Blueprint $table) => $table->dropColumn('loan_type'));
    }
};
