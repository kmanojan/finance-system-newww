<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            if (!Schema::hasColumn('loans', 'loan_code')) {
                $table->string('loan_code', 50)->nullable()->after('id');
            }
        });

        // Backfill existing loans with unique sequential loan codes
        if (Schema::hasTable('loans')) {
            $loans = DB::table('loans')->whereNull('loan_code')->orderBy('id', 'asc')->get();
            foreach ($loans as $loan) {
                $code = 'LN-' . str_pad($loan->id, 4, '0', STR_PAD_LEFT);
                DB::table('loans')->where('id', $loan->id)->update(['loan_code' => $code]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            if (Schema::hasColumn('loans', 'loan_code')) {
                $table->dropColumn('loan_code');
            }
        });
    }
};
