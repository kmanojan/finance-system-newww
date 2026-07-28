<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tax_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('category', ['vat', 'wht', 'cit', 'other']);
            $table->decimal('rate', 5, 2); // e.g. 18.00
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('applies_to'); // invoice_item | commission_payment | loan_interest | other
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            if (!Schema::hasColumn('invoice_items', 'tax_type_id')) {
                $table->foreignId('tax_type_id')->nullable()->constrained('tax_types')->onDelete('set null');
                $table->decimal('tax_rate', 5, 2)->nullable();
                $table->decimal('tax_amount', 15, 2)->nullable();
            }
        });

        Schema::table('commission_payments', function (Blueprint $table) {
            if (!Schema::hasColumn('commission_payments', 'wht_type_id')) {
                $table->foreignId('wht_type_id')->nullable()->constrained('tax_types')->onDelete('set null');
                $table->decimal('wht_rate', 5, 2)->nullable();
                $table->decimal('wht_amount', 15, 2)->nullable();
                $table->decimal('net_paid', 15, 2)->nullable();
            }
        });

        Schema::table('loan_interest_schedule', function (Blueprint $table) {
            if (!Schema::hasColumn('loan_interest_schedule', 'wht_type_id')) {
                $table->foreignId('wht_type_id')->nullable()->constrained('tax_types')->onDelete('set null');
                $table->decimal('wht_rate', 5, 2)->nullable();
                $table->decimal('wht_amount', 15, 2)->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tax_types');
    }
};
