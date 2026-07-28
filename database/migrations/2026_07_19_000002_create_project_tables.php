<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('projects')) {
            Schema::create('projects', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
                $table->string('name');
                $table->string('currency', 10)->default('LKR');
                $table->string('status', 50)->default('active');
                $table->boolean('over_budget_flag')->default(false);
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->decimal('budget_limit', 15, 2)->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('project_party')) {
            Schema::create('project_party', function (Blueprint $table) {
                $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
                $table->foreignId('party_id')->constrained('parties')->cascadeOnDelete();
                $table->string('role', 50);
                $table->decimal('share_percentage', 5, 2)->nullable();
                $table->primary(['project_id', 'party_id', 'role']);
            });
        }

        if (!Schema::hasTable('payment_milestones')) {
            Schema::create('payment_milestones', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
                $table->string('name');
                $table->decimal('amount', 15, 2);
                $table->date('due_date');
                $table->string('status', 50)->default('pending');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('timesheets')) {
            Schema::create('timesheets', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
                $table->text('task_description');
                $table->decimal('hours', 5, 2);
                $table->date('logged_date');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('project_commissions')) {
            Schema::create('project_commissions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
                $table->foreignId('party_id')->constrained('parties')->cascadeOnDelete();
                $table->foreignId('recipient_id')->nullable()->constrained('parties')->nullOnDelete();
                $table->string('commission_type', 50);
                $table->decimal('percentage_value', 5, 2)->nullable();
                $table->string('calculation_basis', 100)->nullable();
                $table->decimal('fixed_amount', 15, 2)->nullable();
                $table->string('currency', 10)->nullable();
                $table->string('trigger_type', 100)->nullable();
                $table->date('effective_from');
                $table->date('effective_to')->nullable();
                $table->string('status', 50)->default('active');
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('commission_payments')) {
            Schema::create('commission_payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_commission_id')->constrained('project_commissions')->cascadeOnDelete();
                $table->decimal('amount', 15, 2);
                $table->date('payment_date');
                $table->string('payment_mode', 50);
                $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
                $table->string('reference_no')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('invoice_schedules')) {
            Schema::create('invoice_schedules', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
                $table->string('name');
                $table->date('from_date');
                $table->date('to_date')->nullable();
                $table->string('frequency', 50)->default('monthly');
                $table->integer('custom_interval_days')->nullable();
                $table->integer('generate_day')->nullable();
                $table->date('next_generation_date')->nullable();
                $table->foreignId('invoice_type_id')->nullable()->constrained('invoice_types')->nullOnDelete();
                $table->string('currency', 10)->default('LKR');
                $table->foreignId('template_id')->nullable()->constrained('document_templates')->nullOnDelete();
                $table->text('notes')->nullable();
                $table->boolean('require_approval')->default(false);
                $table->boolean('auto_adjust_holidays')->default(false);
                $table->boolean('notify_on_generation')->default(false);
                $table->string('status', 50)->default('active');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('invoice_schedule_items')) {
            Schema::create('invoice_schedule_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('schedule_id')->constrained('invoice_schedules')->cascadeOnDelete();
                $table->text('description');
                $table->decimal('quantity', 10, 2)->default(1);
                $table->decimal('unit_price', 15, 2)->default(0);
                $table->decimal('tax_percentage', 5, 2)->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_schedule_items');
        Schema::dropIfExists('invoice_schedules');
        Schema::dropIfExists('commission_payments');
        Schema::dropIfExists('project_commissions');
        Schema::dropIfExists('timesheets');
        Schema::dropIfExists('payment_milestones');
        Schema::dropIfExists('project_party');
        Schema::dropIfExists('projects');
    }
};
