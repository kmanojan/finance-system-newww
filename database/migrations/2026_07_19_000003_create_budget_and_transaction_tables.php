<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('budgets')) {
            Schema::create('budgets', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('scope_type')->nullable();
                $table->unsignedBigInteger('scope_id')->nullable();
                $table->decimal('allocated_amount', 15, 2);
                $table->string('currency', 10);
                $table->string('period', 50);
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('budget_groups')) {
            Schema::create('budget_groups', function (Blueprint $table) {
                $table->id();
                $table->foreignId('budget_id')->constrained('budgets')->cascadeOnDelete();
                $table->string('name');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('budget_items')) {
            Schema::create('budget_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('budget_group_id')->constrained('budget_groups')->cascadeOnDelete();
                $table->string('name');
                $table->decimal('allocated_amount', 15, 2)->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('transactions')) {
            Schema::create('transactions', function (Blueprint $table) {
                $table->id();
                $table->string('type', 50); // income / expense
                $table->foreignId('category_id')->constrained('categories');
                $table->foreignId('department_id')->constrained('departments');
                $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
                $table->string('payment_method', 50)->default('Normal');
                $table->decimal('amount', 15, 2);
                $table->string('currency', 10);
                $table->date('transaction_date');
                $table->text('description')->nullable();
                $table->string('reference_no')->nullable();
                $table->string('payment_status', 50)->default('completed');
                $table->date('due_date')->nullable();
                $table->foreignId('party_id')->nullable()->constrained('parties')->nullOnDelete();
                $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->boolean('reconciled')->default(false);
                $table->timestamp('reconciled_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('budget_transactions')) {
            Schema::create('budget_transactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('budget_id')->constrained('budgets')->cascadeOnDelete();
                $table->foreignId('budget_item_id')->nullable()->constrained('budget_items')->cascadeOnDelete();
                $table->foreignId('transaction_id')->constrained('transactions')->cascadeOnDelete();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_transactions');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('budget_items');
        Schema::dropIfExists('budget_groups');
        Schema::dropIfExists('budgets');
    }
};
