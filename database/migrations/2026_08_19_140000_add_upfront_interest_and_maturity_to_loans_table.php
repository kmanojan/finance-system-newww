<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            if (!Schema::hasColumn('loans', 'maturity_date')) {
                $table->date('maturity_date')->nullable()->after('claimed_date');
            }
            if (!Schema::hasColumn('loans', 'is_upfront_interest')) {
                $table->boolean('is_upfront_interest')->default(false)->after('interest_method');
            }
            if (!Schema::hasColumn('loans', 'upfront_interest_amount')) {
                $table->decimal('upfront_interest_amount', 15, 2)->nullable()->after('is_upfront_interest');
            }
            if (!Schema::hasColumn('loans', 'reminder_days')) {
                $table->integer('reminder_days')->default(3)->after('due_day');
            }
        });

        if (Schema::hasTable('reminders')) {
            Schema::table('reminders', function (Blueprint $table) {
                if (!Schema::hasColumn('reminders', 'title')) {
                    $table->string('title')->nullable()->after('id');
                }
                if (!Schema::hasColumn('reminders', 'notes')) {
                    $table->text('notes')->nullable()->after('status');
                }
                if (!Schema::hasColumn('reminders', 'linked_type')) {
                    $table->string('linked_type', 100)->nullable()->after('notify_before_days');
                }
                if (!Schema::hasColumn('reminders', 'linked_id')) {
                    $table->integer('linked_id')->nullable()->after('linked_type');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $cols = [];
            if (Schema::hasColumn('loans', 'maturity_date')) $cols[] = 'maturity_date';
            if (Schema::hasColumn('loans', 'is_upfront_interest')) $cols[] = 'is_upfront_interest';
            if (Schema::hasColumn('loans', 'upfront_interest_amount')) $cols[] = 'upfront_interest_amount';
            if (Schema::hasColumn('loans', 'reminder_days')) $cols[] = 'reminder_days';
            if (!empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};