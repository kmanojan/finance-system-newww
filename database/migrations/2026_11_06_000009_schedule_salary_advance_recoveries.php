<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_payments', fn (Blueprint $t) => $t->date('recovery_month')->nullable()->index());
        Schema::create('payroll_advance_recoveries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('payroll_payment_id')->constrained()->restrictOnDelete();
            $t->foreignId('payroll_item_id')->constrained()->restrictOnDelete();
            $t->bigInteger('amount_cents');
            $t->timestamps();
            $t->unique(['payroll_payment_id', 'payroll_item_id']);
        });
        // Existing advances stay unscheduled. Attribute previously approved recoveries FIFO,
        // preserving every payroll amount and journal. Never infer a requested deduction month.
        DB::transaction(function () {
            $used = [];
            $items = DB::table('payroll_items as i')->join('payroll_runs as r', 'r.id', '=', 'i.payroll_run_id')
                ->where('r.status', 'approved')->where('i.advance_cents', '>', 0)
                ->select('i.*', 'r.company_id', 'r.currency', 'r.month')->orderBy('r.month')->orderBy('i.id')->get();
            foreach ($items as $item) {
                $remaining = (int) $item->advance_cents;
                $payments = DB::table('payroll_payments')->where('kind', 'advance')->where('status', 'posted')
                    ->where('employee_id', $item->employee_id)->where('company_id', $item->company_id)->where('currency', $item->currency)
                    ->where('paid_on', '<=', Carbon::parse($item->month)->endOfMonth()->toDateString())->orderBy('paid_on')->orderBy('id')->get();
                foreach ($payments as $payment) {
                    $amount = min($remaining, $payment->amount_cents - ($used[$payment->id] ?? 0));
                    if ($amount > 0) {
                        DB::table('payroll_advance_recoveries')->insert(['payroll_payment_id' => $payment->id, 'payroll_item_id' => $item->id, 'amount_cents' => $amount, 'created_at' => now(), 'updated_at' => now()]);
                        $used[$payment->id] = ($used[$payment->id] ?? 0) + $amount;
                        $remaining -= $amount;
                    }
                }
                if ($remaining > 0) {
                    throw new RuntimeException('Existing advance recovery does not reconcile for payroll item '.$item->id.'. Review historical advances before migrating.');
                }
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_advance_recoveries');
        Schema::table('payroll_payments', function (Blueprint $t) {
            $t->dropIndex(['recovery_month']);
            $t->dropColumn('recovery_month');
        });
    }
};
