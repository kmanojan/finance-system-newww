<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('invoices', 'tax_type_id')) {
            DB::statement('ALTER TABLE invoices ADD COLUMN tax_type_id INT NULL');
        }
        if (!Schema::hasColumn('invoices', 'tax_rate')) {
            DB::statement('ALTER TABLE invoices ADD COLUMN tax_rate DECIMAL(5, 2) DEFAULT 0');
        }
        if (!Schema::hasColumn('invoices', 'tax_amount')) {
            DB::statement('ALTER TABLE invoices ADD COLUMN tax_amount DECIMAL(15, 2) DEFAULT 0');
        }
    }

    public function down(): void
    {
        //
    }
};
