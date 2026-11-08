<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_historical_advance_links', function (Blueprint $t) {
            $t->id();
            $t->foreignId('payroll_item_id')->unique()->constrained('payroll_items')->restrictOnDelete();
            $t->foreignId('payroll_payment_id')->unique()->constrained('payroll_payments')->restrictOnDelete();
            $t->string('source_sha256', 64);
            $t->string('source_sheet', 100);
            $t->unsignedInteger('source_row');
            $t->bigInteger('source_amount_cents');
            $t->unsignedBigInteger('created_by');
            $t->timestamps();
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE payroll_historical_advance_links ENABLE ROW LEVEL SECURITY');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_historical_advance_links');
    }
};
