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
        Schema::create('cost_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained();
            $table->enum('type', ['employee', 'server', 'other']);
            $table->foreignId('employee_id')->nullable()->constrained('employees');
            $table->foreignId('server_id')->nullable()->constrained('servers');
            $table->string('cost_center_name')->nullable(); // used when type = other
            $table->date('period_start');
            $table->date('period_end')->nullable();        // null = single-date entry
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3);
            $table->enum('source', ['manual', 'synced'])->default('manual');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cost_allocations');
    }
};
