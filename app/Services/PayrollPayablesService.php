<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/** Employee salary balances derived from the original payroll; no second payable posting. */
class PayrollPayablesService
{
    public static function items()
    {
        $paid = DB::table('payroll_payment_allocations as a')->join('payroll_payments as p', 'p.id', '=', 'a.payroll_payment_id')
            ->where('p.kind', 'salary')->where('p.status', 'posted')->groupBy('a.payroll_item_id')
            ->select('a.payroll_item_id')->selectRaw('SUM(a.amount_cents) as paid_cents');

        return DB::table('payroll_items as i')->join('payroll_runs as r', 'r.id', '=', 'i.payroll_run_id')
            ->join('employees as e', 'e.id', '=', 'i.employee_id')->join('companies as c', 'c.id', '=', 'r.company_id')
            ->leftJoinSub($paid, 'paid', fn ($join) => $join->on('paid.payroll_item_id', '=', 'i.id'))
            ->whereIn('r.status', PayrollService::POSTED_STATUSES)->whereNotNull('e.party_id')
            ->select('i.id', 'i.employee_id', 'e.party_id', 'e.full_name', 'e.employee_code', 'r.id as run_id', 'r.month', 'r.salary_due_date', 'r.status', 'r.currency', 'r.company_id', 'c.name as company_name', 'i.net_cents')
            ->selectRaw('COALESCE(paid.paid_cents, 0) as paid_cents')->orderBy('r.month')->orderBy('e.full_name')->get()
            ->map(function ($item) {
                $item->net_cents = (int) $item->net_cents;
                $item->paid_cents = (int) $item->paid_cents;
                $item->balance_cents = max(0, $item->net_cents - $item->paid_cents);

                return $item;
            });
    }

    public static function payments()
    {
        return DB::table('payroll_payment_allocations as a')->join('payroll_payments as p', 'p.id', '=', 'a.payroll_payment_id')
            ->join('payroll_items as i', 'i.id', '=', 'a.payroll_item_id')->join('payroll_runs as r', 'r.id', '=', 'i.payroll_run_id')
            ->join('employees as e', 'e.id', '=', 'i.employee_id')->whereIn('r.status', PayrollService::POSTED_STATUSES)
            ->whereColumn('p.payroll_run_id', 'r.id')->where('p.kind', 'salary')->where('p.status', 'posted')->whereNotNull('e.party_id')
            ->select('e.party_id', 'r.month', 'r.currency', 'p.id', 'p.paid_on', 'p.reference', 'a.amount_cents')->get();
    }
}
