<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Imported cashbooks lack the optional fields used by transaction and bank reports.
        // Existing cash entries are completed receipts/payments; preserve their amounts and IDs.
        if (! Schema::hasColumn('transactions', 'payment_status')) {
            Schema::table('transactions', fn (Blueprint $t) => $t->string('payment_status', 50)->default('completed'));
        }
        foreach (['project_id', 'party_id', 'user_id'] as $field) {
            if (! Schema::hasColumn('transactions', $field)) {
                Schema::table('transactions', fn (Blueprint $t) => $t->unsignedBigInteger($field)->nullable());
            }
        }
        if (! Schema::hasColumn('transactions', 'due_date')) {
            Schema::table('transactions', fn (Blueprint $t) => $t->date('due_date')->nullable());
        }
        if (! Schema::hasColumn('transactions', 'reconciled_at')) {
            Schema::table('transactions', fn (Blueprint $t) => $t->timestamp('reconciled_at')->nullable());
        }
    }

    public function down(): void
    {
        // Keep cashbook metadata on rollback; removing it could discard later reconciliation data.
    }
};
