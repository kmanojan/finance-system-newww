<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_runs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('department_id')->constrained()->restrictOnDelete();
            $t->date('month');
            $t->string('currency', 3);
            $t->string('active_key')->nullable()->unique();
            $t->string('status')->default('draft');
            $t->date('salary_due_date');
            $t->date('statutory_due_date');
            $t->date('tax_due_date');
            $t->unsignedBigInteger('created_by');
            $t->unsignedBigInteger('approved_by')->nullable();
            $t->timestamp('approved_at')->nullable();
            $t->unsignedBigInteger('journal_entry_id')->nullable();
            $t->unsignedBigInteger('reversal_journal_id')->nullable();
            $t->text('notes')->nullable();
            $t->text('void_reason')->nullable();
            $t->timestamps();
        });
        Schema::create('payroll_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('payroll_run_id')->constrained()->restrictOnDelete();
            $t->foreignId('employee_id')->constrained()->restrictOnDelete();
            $t->string('active_key')->nullable()->unique();
            $t->json('snapshot');
            $t->json('adjustments');
            $t->json('calculation');
            foreach (['gross', 'net', 'epf', 'etf', 'apit', 'other', 'advance', 'cost'] as $name) {
                $t->bigInteger($name.'_cents')->default(0);
            }
            $t->boolean('reviewed')->default(false);
            $t->timestamps();
            $t->unique(['payroll_run_id', 'employee_id']);
        });
        Schema::create('payroll_payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('payroll_run_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('employee_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('department_id')->constrained()->restrictOnDelete();
            $t->string('kind');
            $t->string('currency', 3);
            $t->bigInteger('amount_cents');
            $t->date('paid_on');
            $t->string('reference');
            $t->string('request_key')->unique();
            $t->foreignId('bank_account_id')->nullable()->constrained()->restrictOnDelete();
            $t->unsignedBigInteger('transaction_id')->unique();
            $t->unsignedBigInteger('journal_entry_id');
            $t->unsignedBigInteger('created_by');
            $t->string('receipt_path')->nullable();
            $t->string('receipt_name')->nullable();
            $t->string('status')->default('posted');
            $t->date('reversed_on')->nullable();
            $t->unsignedBigInteger('reversal_transaction_id')->nullable()->unique();
            $t->unsignedBigInteger('reversal_journal_id')->nullable();
            $t->text('reversal_reason')->nullable();
            $t->timestamps();
        });
        Schema::create('payroll_payment_allocations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('payroll_payment_id')->constrained()->restrictOnDelete();
            $t->foreignId('payroll_item_id')->constrained()->restrictOnDelete();
            $t->bigInteger('amount_cents');
            $t->unique(['payroll_payment_id', 'payroll_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_payment_allocations');
        Schema::dropIfExists('payroll_payments');
        Schema::dropIfExists('payroll_items');
        Schema::dropIfExists('payroll_runs');
    }
};
