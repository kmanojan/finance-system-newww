<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salary_components', fn (Blueprint $table) => $table->string('payroll_input_mode', 30)->default('display'));
    }

    public function down(): void
    {
        Schema::table('salary_components', fn (Blueprint $table) => $table->dropColumn('payroll_input_mode'));
    }
};
