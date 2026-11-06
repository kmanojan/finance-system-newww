<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class FinancialReportingService
{
    public static function baseCurrency(): string
    {
        return DB::table('companies')->value('base_currency') ?? 'LKR';
    }

    public static function convert(float $amount, ?string $currency, string $date, ?string $base = null): float
    {
        $base ??= self::baseCurrency();
        $currency = $currency ?: $base;
        if ($currency === $base || $amount == 0) {
            return round($amount, 2);
        }
        $rate = DB::table('currency_exchange_rates')->where('base_currency', $base)->where('target_currency', $currency)->where('rate_date', '<=', $date)->orderByDesc('rate_date')->orderByDesc('id')->value('rate');
        if ($rate > 0) {
            return round($amount / $rate, 2);
        }
        $inverse = DB::table('currency_exchange_rates')->where('base_currency', $currency)->where('target_currency', $base)->where('rate_date', '<=', $date)->orderByDesc('rate_date')->orderByDesc('id')->value('rate');
        if ($inverse > 0) {
            return round($amount * $inverse, 2);
        }
        throw ValidationException::withMessages(['currency' => "No {$currency}/{$base} exchange rate is available on or before {$date}. Add the historical rate before running this report."]);
    }

    public static function outstanding($invoice, string $date): float
    {
        $paid = DB::table('payment_allocations')->join('payments', 'payments.id', '=', 'payment_allocations.payment_id')->where('payment_allocations.invoice_id', $invoice->id)->where('payments.payment_date', '<=', $date)->sum('payment_allocations.amount');

        return max(0, round($invoice->amount - $paid, 2));
    }

    public static function issuedInvoices(string $date)
    {
        // Older installations have issue_date only; SQL COALESCE cannot reference an absent column.
        $invoiceDate = Schema::hasColumn('invoices', 'invoice_date')
            ? 'COALESCE(issue_date, invoice_date, DATE(created_at))'
            : 'COALESCE(issue_date, DATE(created_at))';

        return DB::table('invoices')->whereNotIn('status', ['draft', 'pending_approval', 'rejected', 'cancelled'])->whereRaw($invoiceDate.' <= ?', [$date]);
    }

    public static function scope($query, string $prefix = '')
    {
        if (request('department_id')) {
            $query->where($prefix.'department_id', request('department_id'));
        }
        if (request('company_id')) {
            $query->whereIn($prefix.'department_id', DB::table('departments')->where('company_id', request('company_id'))->select('id'));
        }

        return $query;
    }

    public static function transactions(string $from, string $to)
    {
        return self::scope(DB::table('transactions')->leftJoin('categories', 'categories.id', '=', 'transactions.category_id'), 'transactions.')
            ->whereBetween('transaction_date', [$from, $to])->where('payment_status', 'completed')
            ->select('transactions.*', 'categories.name as category_name')->get()->map(function ($row) {
                $row->base_amount = self::convert($row->amount, $row->currency, $row->transaction_date);
                $row->financing = in_array($row->category_name, ['Loan Disbursement', 'Loan Principal Repayment']);

                return $row;
            });
    }

    public static function ledgerBalances(string $from, string $to, bool $excludeClosing = false)
    {
        $query = DB::table('journal_entry_lines as lines')->join('journal_entries as entries', 'entries.id', '=', 'lines.journal_entry_id')->join('accounts', 'accounts.id', '=', 'lines.account_id')
            ->whereDate('entries.entry_date', '>=', $from)->whereDate('entries.entry_date', '<=', $to);
        if ($excludeClosing) {
            $query->where(fn ($q) => $q->whereNull('entries.reference_type')->orWhere('entries.reference_type', '!=', 'YearEndClose'));
        }
        if (request('company_id')) {
            $query->where('accounts.company_id', request('company_id'));
        }

        return $query->select('lines.*', 'entries.entry_date', 'accounts.code', 'accounts.name', 'accounts.type')->get()->groupBy('account_id')->map(function ($lines) {
            $first = $lines->first();
            $debit = $lines->sum(fn ($line) => self::convert($line->debit, $line->currency, $line->entry_date));
            $credit = $lines->sum(fn ($line) => self::convert($line->credit, $line->currency, $line->entry_date));

            return (object) ['id' => $first->account_id, 'code' => $first->code, 'name' => $first->name, 'type' => $first->type, 'debit' => $debit, 'credit' => $credit, 'balance' => round($debit - $credit, 2)];
        })->sortBy('code')->values();
    }

    public static function unpostedCount(): int
    {
        $invoices = DB::table('invoices')->whereNotIn('status', ['draft', 'pending_approval', 'rejected', 'cancelled'])->whereNotExists(fn ($q) => $q->selectRaw('1')->from('journal_entries')->where('reference_type', 'Invoice')->whereColumn('reference_id', 'invoices.id'))->count();
        $transactions = DB::table('transactions')->where('payment_status', 'completed')->whereNull('project_id')->whereNotIn('category_id', DB::table('categories')->where('name', 'Vendor payment')->select('id'))->when(Schema::hasTable('payroll_payments'), fn ($q) => $q->whereNotExists(fn ($p) => $p->selectRaw('1')->from('payroll_payments')->whereColumn('transaction_id', 'transactions.id')->orWhereColumn('reversal_transaction_id', 'transactions.id')))->whereNotExists(fn ($q) => $q->selectRaw('1')->from('journal_entries')->whereIn('reference_type', ['Transaction', 'PayrollPayment'])->whereColumn('reference_id', 'transactions.id'))->count();

        return $invoices + $transactions;
    }
}
