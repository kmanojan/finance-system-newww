<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('change_requests', 'external_links')) {
            Schema::table('change_requests', fn (Blueprint $t) => $t->text('external_links')->nullable());
        }
        foreach (['invoice_items' => ['tax_type_id', 'tax_rate', 'tax_amount'], 'commission_payments' => ['wht_type_id', 'wht_rate', 'wht_amount', 'net_paid'], 'loan_interest_schedule' => ['wht_type_id', 'wht_rate', 'wht_amount']] as $table => $columns) {
            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    Schema::table($table, function (Blueprint $t) use ($column) {
                        if (str_ends_with($column, '_id')) {
                            $t->unsignedBigInteger($column)->nullable();
                        } else {
                            $t->decimal($column, str_ends_with($column, '_rate') ? 8 : 15, 2)->nullable();
                        }
                    });
                }
            }
        }
    }

    public function down(): void {}
};
