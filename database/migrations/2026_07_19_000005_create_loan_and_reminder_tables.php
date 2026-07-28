<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('loans')) {
            Schema::create('loans', function (Blueprint $table) {
                $table->id();
                $table->foreignId('party_id')->nullable()->constrained('parties')->nullOnDelete();
                $table->string('lender_name');
                $table->decimal('principal_amount', 15, 2);
                $table->string('currency', 10);
                $table->text('purpose')->nullable();
                $table->date('claimed_date')->nullable();
                $table->integer('term_months')->nullable();
                $table->string('interest_method', 50)->nullable();
                $table->decimal('interest_amount', 15, 2)->nullable();
                $table->decimal('interest_rate', 5, 2)->nullable();
                $table->string('rate_basis', 50)->nullable();
                $table->decimal('total_interest', 15, 2)->nullable();
                $table->integer('due_day')->nullable();
                $table->string('frequency', 50)->nullable();
                $table->string('guarantor')->nullable();
                $table->text('collateral')->nullable();
                $table->decimal('outstanding_principal', 15, 2)->default(0);
                $table->decimal('monthly_installment', 15, 2)->default(0);
                $table->date('start_date')->nullable();
                $table->date('maturity_date')->nullable();
                $table->string('status', 50)->default('active');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('loan_interest_schedule')) {
            Schema::create('loan_interest_schedule', function (Blueprint $table) {
                $table->id();
                $table->foreignId('loan_id')->constrained('loans')->cascadeOnDelete();
                $table->date('due_date');
                $table->decimal('interest_amount', 15, 2);
                $table->decimal('paid_amount', 15, 2)->default(0);
                $table->date('paid_date')->nullable();
                $table->string('status', 50)->default('pending');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('loan_principal_records')) {
            Schema::create('loan_principal_records', function (Blueprint $table) {
                $table->id();
                $table->foreignId('loan_id')->constrained('loans')->cascadeOnDelete();
                $table->string('record_type', 50); // draw / repayment
                $table->decimal('amount', 15, 2);
                $table->date('record_date')->nullable();
                $table->string('payment_mode', 50)->nullable();
                $table->string('reference_no')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('reminders')) {
            Schema::create('reminders', function (Blueprint $table) {
                $table->id();
                $table->string('title')->nullable();
                $table->string('type', 50)->default('custom');
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->string('reference_type')->nullable();
                $table->date('due_date');
                $table->integer('notify_before_days')->default(0);
                $table->string('status', 50)->default('pending');
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('change_requests')) {
            Schema::create('change_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
                $table->text('description');
                $table->decimal('amount', 15, 2)->nullable();
                $table->string('currency', 10)->nullable();
                $table->string('status', 50)->default('pending');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('notes')) {
            Schema::create('notes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('notable_id');
                $table->string('notable_type');
                $table->text('content');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('interactions')) {
            Schema::create('interactions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('interactionable_id');
                $table->string('interactionable_type');
                $table->string('type', 50); // call / meeting / email
                $table->text('summary');
                $table->dateTime('interaction_date');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('interactions');
        Schema::dropIfExists('notes');
        Schema::dropIfExists('change_requests');
        Schema::dropIfExists('reminders');
        Schema::dropIfExists('loan_principal_records');
        Schema::dropIfExists('loan_interest_schedule');
        Schema::dropIfExists('loans');
    }
};
