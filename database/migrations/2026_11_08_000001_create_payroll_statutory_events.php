<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_statutory_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained()->restrictOnDelete();
            $table->string('kind', 10);
            $table->string('action', 20);
            $table->date('event_on');
            $table->date('prepared_on');
            $table->date('payment_due_on');
            $table->bigInteger('prepared_amount_cents');
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->string('evidence_path')->nullable();
            $table->string('evidence_name')->nullable();
            $table->string('request_key')->unique();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            $table->index(['payroll_run_id', 'kind', 'id'], 'payroll_statutory_run_kind_index');
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE payroll_statutory_events ENABLE ROW LEVEL SECURITY');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_statutory_events');
    }
};
