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
        // Add compensation and bank details to employees table
        Schema::table('employees', function (Blueprint $table) {
            $table->decimal('basic_salary', 15, 2)->default(0.00)->after('status');
            $table->string('currency', 3)->default('LKR')->after('basic_salary');
            $table->string('payment_mode', 50)->default('bank_transfer')->after('currency');
            $table->string('bank_name', 100)->nullable()->after('payment_mode');
            $table->string('bank_account_no', 50)->nullable()->after('bank_name');
            $table->string('bank_branch', 100)->nullable()->after('bank_account_no');
            $table->string('epf_number', 50)->nullable()->after('bank_branch');
        });

        // 1. Salary Component Master
        Schema::create('salary_components', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('type'); // 'earning', 'deduction', 'employer_contribution'
            $table->string('calculation_type')->default('fixed_amount'); // 'fixed_amount', 'percentage'
            $table->string('calculation_base')->default('on_basic'); // 'on_basic', 'on_epf_base', 'on_gross', 'custom'
            $table->decimal('default_value', 15, 2)->default(0.00);
            $table->boolean('is_epf_eligible')->default(false); // counts toward EPF/ETF base
            $table->boolean('is_taxable')->default(true); // subject to APIT
            $table->boolean('is_statutory')->default(false); // statutory rule like EPF/ETF
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->text('description')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        // 2. Employee Specific Salary Component Mapping
        Schema::create('employee_salary_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('salary_component_id')->constrained('salary_components')->cascadeOnDelete();
            $table->decimal('value', 15, 2)->default(0.00); // Fixed amount or percentage value
            $table->boolean('is_enabled')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'salary_component_id'], 'emp_salary_comp_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_salary_components');
        Schema::dropIfExists('salary_components');

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn([
                'basic_salary',
                'currency',
                'payment_mode',
                'bank_name',
                'bank_account_no',
                'bank_branch',
                'epf_number',
            ]);
        });
    }
};
