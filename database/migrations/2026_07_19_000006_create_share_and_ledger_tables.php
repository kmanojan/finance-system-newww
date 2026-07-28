<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('share_links')) {
            Schema::create('share_links', function (Blueprint $table) {
                $table->id();
                $table->string('token', 100)->unique();
                $table->unsignedBigInteger('shareable_id');
                $table->string('shareable_type');
                $table->string('password')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->integer('max_uses')->nullable();
                $table->integer('use_count')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('share_link_visits')) {
            Schema::create('share_link_visits', function (Blueprint $table) {
                $table->id();
                $table->foreignId('share_link_id')->constrained('share_links')->cascadeOnDelete();
                $table->string('ip_address', 50)->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('attachments')) {
            Schema::create('attachments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('attachable_id');
                $table->string('attachable_type');
                $table->string('file_path');
                $table->string('file_name');
                $table->string('mime_type', 100)->nullable();
                $table->integer('file_size')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('activity_logs')) {
            Schema::create('activity_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('action', 100);
                $table->string('module', 100)->nullable();
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->text('description')->nullable();
                $table->string('ip_address', 50)->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('journal_entries')) {
            Schema::create('journal_entries', function (Blueprint $table) {
                $table->id();
                $table->string('entry_no', 50)->unique();
                $table->date('entry_date');
                $table->text('description')->nullable();
                $table->string('status', 50)->default('posted');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('journal_entry_lines')) {
            Schema::create('journal_entry_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('journal_entry_id')->constrained('journal_entries')->cascadeOnDelete();
                $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
                $table->decimal('debit', 15, 2)->default(0);
                $table->decimal('credit', 15, 2)->default(0);
                $table->text('memo')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entry_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('attachments');
        Schema::dropIfExists('share_link_visits');
        Schema::dropIfExists('share_links');
    }
};
