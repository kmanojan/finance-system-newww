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
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('external_id')->unique(); // "id" from the source API
            $table->string('employee_code')->nullable();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('full_name');
            $table->string('personal_email')->nullable();
            $table->string('mobile_phone')->nullable();
            $table->string('profile_picture_url')->nullable();
            $table->string('status')->default('active');       // active / inactive
            $table->string('user_type')->nullable();
            $table->string('job_position')->nullable();
            $table->string('role')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
