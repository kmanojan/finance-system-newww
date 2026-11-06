<?php

namespace App\Services;

use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalaryAdvanceService
{
    public static function payments()
    {
        return DB::table('payroll_payments as p')->where('p.kind', 'advance')->select('p.*')->selectSub(function ($q) {
            $q->from('payroll_advance_recoveries as a')->join('payroll_items as i', 'i.id', '=', 'a.payroll_item_id')->join('payroll_runs as r', 'r.id', '=', 'i.payroll_run_id')
                ->whereColumn('a.payroll_payment_id', 'p.id')->where('r.status', 'approved')->selectRaw('COALESCE(SUM(a.amount_cents), 0)');
        }, 'recovered_cents');
    }

    public static function eligible(array $employees, int $company, string $currency, string $month, bool $scheduledOnly = false)
    {
        $month = Carbon::parse($month)->startOfMonth();

        return self::payments()->where('p.status', 'posted')->whereIn('p.employee_id', $employees)->where('p.company_id', $company)->where('p.currency', $currency)
            ->where('p.paid_on', '<=', $month->copy()->endOfMonth()->toDateString())
            ->where(function ($q) use ($month, $scheduledOnly) {
                $q->where('p.recovery_month', '<=', $month->toDateString());
                if (! $scheduledOnly) {
                    $q->orWhereNull('p.recovery_month');
                }
            })->orderByRaw('COALESCE(p.recovery_month, p.paid_on)')->orderBy('p.id')->get()
            ->map(function ($p) {
                $p->remaining_cents = (int) $p->amount_cents - (int) $p->recovered_cents;

                return $p;
            })
            ->filter(fn ($p) => $p->remaining_cents > 0)->groupBy('employee_id');
    }

    public static function scheduledTotals(array $employees, ?int $company, ?string $month): array
    {
        if (! $company || ! $month || ! $employees) {
            return [];
        }

        return self::eligible($employees, $company, 'LKR', $month, true)->map(fn ($payments) => $payments->sum('remaining_cents'))->all();
    }

    public static function proposal(array $snapshot, array $adjustments, int $due): array
    {
        $base = PayrollCalculationService::calculate($snapshot, array_replace($adjustments, ['advance' => 0]));
        $amount = min($due, $base['net']);
        $adjustments['advance'] = $amount / 100;

        return ['adjustments' => $adjustments, 'calculation' => PayrollCalculationService::calculate($snapshot, $adjustments)];
    }

    public static function allocate(object $run, object $item): void
    {
        $remaining = (int) $item->advance_cents;
        $payments = self::eligible([$item->employee_id], $run->company_id, $run->currency, $run->month)[$item->employee_id] ?? collect();
        foreach ($payments as $payment) {
            $amount = min($remaining, $payment->remaining_cents);
            if ($amount > 0) {
                DB::table('payroll_advance_recoveries')->insert(['payroll_payment_id' => $payment->id, 'payroll_item_id' => $item->id, 'amount_cents' => $amount, 'created_at' => now(), 'updated_at' => now()]);
                $remaining -= $amount;
            }
        }
        if ($remaining > 0) {
            throw ValidationException::withMessages(['advance' => $item->snapshot['name'].': advance recovery exceeds the balance eligible for this payroll month. Return to draft and review it.']);
        }
    }

    public static function schedule(int $id, string $month): void
    {
        DB::transaction(function () use ($id, $month) {
            $p = DB::table('payroll_payments')->where('id', $id)->lockForUpdate()->first() ?? abort(404);
            abort_unless($p->kind === 'advance', 404);
            Employee::whereKey($p->employee_id)->lockForUpdate()->firstOrFail();
            $record = self::payments()->where('p.id', $id)->first();
            if ($p->status !== 'posted' || $record->recovered_cents > 0) {
                throw ValidationException::withMessages(['recovery_month' => 'Only an unrecovered, posted advance can be rescheduled.']);
            }
            self::validateMonth($month, $p->paid_on);
            DB::table('payroll_payments')->where('id', $id)->update(['recovery_month' => $month.'-01', 'updated_at' => now()]);
            ActivityLogService::logUpdate('PayrollPayment', $id, ['recovery_month' => $p->recovery_month], ['recovery_month' => $month.'-01']);
        });
    }

    public static function validateMonth(string $month, string $paidOn): void
    {
        if ($month < substr($paidOn, 0, 7)) {
            throw ValidationException::withMessages(['recovery_month' => 'The deduction month cannot be before the advance payment month.']);
        }
    }
}
