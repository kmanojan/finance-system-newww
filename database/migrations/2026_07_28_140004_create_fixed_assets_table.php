<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixed_assets', function (Blueprint $table) {
            $table->id();
            $table->string('asset_name');
            $table->string('asset_code')->unique();
            $table->string('category'); // 'computers', 'furniture', 'vehicles', 'machinery'
            $table->date('purchase_date');
            $table->decimal('purchase_cost', 15, 2);
            $table->decimal('salvage_value', 15, 2)->default(0.00);
            $table->integer('lifespan_years');
            $table->enum('depreciation_method', ['straight_line', 'reducing_balance'])->default('straight_line');
            $table->decimal('accumulated_depreciation', 15, 2)->default(0.00);
            $table->enum('status', ['active', 'fully_depreciated', 'disposed'])->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_assets');
    }
};
