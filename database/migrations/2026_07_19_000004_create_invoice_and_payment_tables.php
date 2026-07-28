<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('invoices')) {
            Schema::create('invoices', function (Blueprint $table) {
                $table->id();
                $table->string('invoice_no')->unique();
                $table->foreignId('client_id')->constrained('parties');
                $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
                $table->foreignId('department_id')->constrained('departments');
                $table->foreignId('template_id')->nullable()->constrained('document_templates')->nullOnDelete();
                $table->unsignedBigInteger('schedule_id')->nullable();
                $table->decimal('amount', 15, 2);
                $table->decimal('subtotal', 15, 2)->default(0);
                $table->decimal('advance_paid', 15, 2)->default(0);
                $table->decimal('grand_total', 15, 2)->default(0);
                $table->string('currency', 10);
                $table->string('status', 50)->default('draft');
                $table->string('signee_name')->nullable();
                $table->string('signee_title')->nullable();
                $table->string('signature_image')->nullable();
                $table->json('template_snapshot')->nullable();
                $table->unsignedBigInteger('tax_type_id')->nullable();
                $table->decimal('tax_rate', 5, 2)->default(0);
                $table->decimal('tax_amount', 15, 2)->default(0);
                $table->date('due_date')->nullable();
                $table->date('issue_date')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('invoice_items')) {
            Schema::create('invoice_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
                $table->foreignId('invoice_type_id')->constrained('invoice_types');
                $table->text('description');
                $table->decimal('qty', 10, 2)->default(1);
                $table->decimal('unit_price', 15, 2);
                $table->string('currency', 10);
                $table->decimal('tax_percentage', 5, 2)->default(0);
                $table->decimal('total', 15, 2);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('payments')) {
            Schema::create('payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
                $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
                $table->decimal('total_amount', 15, 2);
                $table->string('currency', 10);
                $table->date('payment_date');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('payment_allocations')) {
            Schema::create('payment_allocations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
                $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
                $table->decimal('amount', 15, 2);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('payment_modes')) {
            Schema::create('payment_modes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
                $table->string('mode', 50); // cash, card, cheque, bank_transfer
                $table->decimal('amount', 15, 2);
                $table->string('bank_name')->nullable();
                $table->string('cheque_no')->nullable();
                $table->date('cheque_date')->nullable();
                $table->string('cheque_status', 50)->nullable();
                $table->string('reference_no')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('cheques')) {
            Schema::create('cheques', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('transaction_id')->nullable();
                $table->string('cheque_number', 100);
                $table->date('cheque_date');
                $table->string('bank_name');
                $table->decimal('amount', 15, 2);
                $table->string('currency', 10)->default('LKR');
                $table->string('status', 50)->default('pending_deposit');
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cheques');
        Schema::dropIfExists('payment_modes');
        Schema::dropIfExists('payment_allocations');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
    }
};
