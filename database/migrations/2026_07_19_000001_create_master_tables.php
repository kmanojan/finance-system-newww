<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('companies')) {
            Schema::create('companies', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('logo_url')->nullable();
                $table->string('base_currency', 10)->default('LKR');
                $table->text('registration_details')->nullable();
                $table->text('tax_details')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('departments')) {
            Schema::create('departments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->foreignId('parent_id')->nullable()->constrained('departments')->nullOnDelete();
                $table->string('name');
                $table->string('code', 50)->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('categories')) {
            Schema::create('categories', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('type', 50); // income / expense
                $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
                $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('invoice_types')) {
            Schema::create('invoice_types', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('maps_to', 50); // income / expense
                $table->foreignId('default_category_id')->nullable()->constrained('categories')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('document_templates')) {
            Schema::create('document_templates', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
                $table->string('name');
                $table->string('header_image_url')->nullable();
                $table->string('footer_image_url')->nullable();
                $table->string('background_image_url')->nullable();
                $table->text('description')->nullable();
                $table->text('company_details')->nullable();
                $table->text('bank_details')->nullable();
                $table->text('terms_conditions')->nullable();
                $table->json('other_details')->nullable();
                $table->boolean('is_default')->default(false);
                $table->string('language', 50)->default('English');
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('bank_accounts')) {
            Schema::create('bank_accounts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
                $table->string('bank_name');
                $table->string('account_no');
                $table->string('currency', 10);
                $table->decimal('opening_balance', 15, 2)->default(0);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('parties')) {
            Schema::create('parties', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('types'); // comma separated e.g. client, vendor
                $table->string('contact_person')->nullable();
                $table->string('email')->nullable();
                $table->string('phone', 50)->nullable();
                $table->text('address')->nullable();
                $table->string('tax_id', 100)->nullable();
                $table->string('default_commission_type', 50)->nullable();
                $table->decimal('default_commission_value', 15, 2)->nullable();
                $table->text('notes')->nullable();
                $table->string('status', 50)->default('active');
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('tags')) {
            Schema::create('tags', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('color', 50)->default('#5243E8');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('taggables')) {
            Schema::create('taggables', function (Blueprint $table) {
                $table->foreignId('tag_id')->constrained('tags')->cascadeOnDelete();
                $table->unsignedBigInteger('taggable_id');
                $table->string('taggable_type');
                $table->primary(['tag_id', 'taggable_id', 'taggable_type']);
            });
        }

        if (!Schema::hasTable('currencies')) {
            Schema::create('currencies', function (Blueprint $table) {
                $table->id();
                $table->string('code', 3)->unique();
                $table->string('name', 100);
                $table->string('symbol', 10)->default('$');
                $table->boolean('is_active')->default(true);
                $table->boolean('is_base')->default(false);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('currency_exchange_rates')) {
            Schema::create('currency_exchange_rates', function (Blueprint $table) {
                $table->id();
                $table->string('base_currency', 3);
                $table->string('target_currency', 3);
                $table->decimal('rate', 18, 6);
                $table->date('rate_date');
                $table->string('source', 50)->default('api');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('currency_exchange_rates');
        Schema::dropIfExists('currencies');
        Schema::dropIfExists('taggables');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('parties');
        Schema::dropIfExists('bank_accounts');
        Schema::dropIfExists('document_templates');
        Schema::dropIfExists('invoice_types');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('departments');
        Schema::dropIfExists('companies');
    }
};
