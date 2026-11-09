<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_historical_loan_reconciliations', function (Blueprint $table) {
            $table->foreignId('payroll_item_id')->primary()->constrained()->restrictOnDelete();
            $table->foreignId('journal_line_id')->constrained('journal_entry_lines')->restrictOnDelete();
            $table->index('journal_line_id');
            $table->string('source_reference');
            $table->bigInteger('balance_cents');
            $table->bigInteger('deduction_cents');
            $table->unsignedBigInteger('verified_by');
            $table->timestamps();
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE payroll_historical_loan_reconciliations ENABLE ROW LEVEL SECURITY');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_historical_loan_reconciliations');
    }
};
