<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('po_number')->unique();
            $table->foreignId('vendor_id')->constrained('parties')->onDelete('cascade');
            $table->foreignId('department_id')->constrained('departments')->onDelete('cascade');
            $table->enum('status', ['draft', 'pending_approval', 'approved', 'billed', 'cancelled'])->default('draft');
            $table->decimal('total_amount', 15, 2);
            $table->string('currency', 10)->default('LKR');
            $table->date('issue_date');
            $table->timestamps();
        });

        Schema::create('vendor_bills', function (Blueprint $table) {
            $table->id();
            $table->string('bill_number');
            $table->foreignId('vendor_id')->constrained('parties')->onDelete('cascade');
            $table->foreignId('po_id')->nullable()->constrained('purchase_orders')->onDelete('set null');
            $table->foreignId('department_id')->constrained('departments')->onDelete('cascade');
            $table->decimal('amount', 15, 2);
            $table->string('currency', 10)->default('LKR');
            $table->enum('status', ['unpaid', 'partially_paid', 'paid', 'overdue'])->default('unpaid');
            $table->date('issue_date');
            $table->date('due_date');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_bills');
        Schema::dropIfExists('purchase_orders');
    }
};
