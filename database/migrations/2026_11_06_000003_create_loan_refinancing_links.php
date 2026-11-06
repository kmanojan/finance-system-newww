<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_refinancing_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('principal_record_id')->constrained('loan_principal_records')->restrictOnDelete();
            $table->foreignId('source_loan_id')->constrained('loans')->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('notes', 1000)->nullable();
            $table->timestamps();
            $table->unique(['principal_record_id', 'source_loan_id'], 'loan_refinancing_record_source_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_refinancing_links');
    }
};
