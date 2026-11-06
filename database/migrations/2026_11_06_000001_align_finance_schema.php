<?php

use App\Models\Currency;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('vendor_payments')) {
            Schema::create('vendor_payments', function (Blueprint $t) {
                $t->id();
                $t->foreignId('vendor_bill_id')->constrained('vendor_bills');
                $t->foreignId('bank_account_id')->nullable()->constrained('bank_accounts');
                $t->decimal('amount', 15, 2);
                $t->date('payment_date');
                $t->string('reference_no')->nullable();
                $t->timestamps();
            });
        }
        $columns = ['payment_milestones' => ['calculation_type' => 'string', 'percentage' => 'money', 'invoice_id' => 'id', 'skip_reason' => 'text'], 'cost_allocations' => ['status' => 'string'], 'bank_statement_imports' => ['source_fingerprint' => 'string'], 'reminders' => ['snoozed_until' => 'timestamp'],
            'invoices' => ['discount_type' => 'string', 'discount_value' => 'money', 'discount_amount' => 'money'],
            'share_links' => ['audience' => 'string', 'password_hash' => 'string', 'allow_downloads' => 'bool', 'notify_on_view' => 'bool', 'revoked_at' => 'timestamp', 'created_by' => 'id'],
            'share_link_visits' => ['referrer' => 'text', 'viewed_at' => 'timestamp'],
            'attachments' => ['model_id' => 'id', 'model_type' => 'string', 'uploaded_by' => 'id'],
            'activity_logs' => ['model_id' => 'id', 'model_type' => 'string', 'old_value' => 'json', 'new_value' => 'json'],
            'journal_entries' => ['reference_id' => 'id', 'reference_type' => 'string'],
            'journal_entry_lines' => ['account_name' => 'string', 'account_type' => 'string', 'currency' => 'string'],
            'payment_modes' => ['bank_account_id' => 'id'],
        ];
        foreach ($columns as $table => $fields) {
            foreach ($fields as $name => $type) {
                if (Schema::hasColumn($table, $name)) {
                    continue;
                }
                Schema::table($table, function (Blueprint $t) use ($name, $type) {
                    match ($type) {
                        'money' => $t->decimal($name, 15, 2)->default(0),
                        'bool' => $t->boolean($name)->default(false),
                        'id' => $t->unsignedBigInteger($name)->nullable(),
                        'json' => $t->json($name)->nullable(),
                        'timestamp' => $t->timestamp($name)->nullable(),
                        'text' => $t->text($name)->nullable(),
                        default => $t->string($name)->nullable(),
                    };
                });
            }
        }
        foreach (['attachable_id', 'attachable_type'] as $column) {
            if (Schema::hasColumn('attachments', $column)) {
                Schema::table('attachments', function (Blueprint $t) use ($column) {
                    if ($column === 'attachable_id') {
                        $t->unsignedBigInteger($column)->nullable()->change();
                    } else {
                        $t->string($column)->nullable()->change();
                    }
                });
            }
        }
        if (DB::table('currencies')->count() === 0) {
            Currency::seedDefaultCurrencies();
        }
        if (Schema::hasColumn('attachments', 'attachable_id') && Schema::hasColumn('attachments', 'attachable_type')) {
            DB::table('attachments')->whereNull('model_id')->update(['model_id' => DB::raw('attachable_id'), 'model_type' => DB::raw('attachable_type')]);
        }
        DB::table('cost_allocations')->whereNull('status')->update(['status' => 'approved']);
        DB::table('share_links')->whereNull('audience')->update(['audience' => 'client']);
        if (Schema::hasColumn('share_links', 'password')) {
            DB::table('share_links')->whereNull('password_hash')->whereNotNull('password')->update(['password_hash' => DB::raw('password')]);
        }
        if (Schema::hasColumn('activity_logs', 'reference_id') && Schema::hasColumn('activity_logs', 'module')) {
            DB::table('activity_logs')->whereNull('model_id')->update(['model_id' => DB::raw('reference_id'), 'model_type' => DB::raw('module')]);
        }
    }

    public function down(): void
    {
        // Intentionally retain compatibility columns and financial history on rollback.
    }
};
