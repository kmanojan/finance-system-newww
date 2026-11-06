<?php

namespace App\Services;

use App\Models\JournalEntry;
use Illuminate\Support\Facades\DB;

class InvoicePostingService
{
    public static function sync(int $id): void
    {
        $invoice = DB::table('invoices')->where('id', $id)->lockForUpdate()->first();
        if (! $invoice) {
            return;
        }
        $entry = JournalEntry::with('lines')->where('reference_type', 'Invoice')->where('reference_id', $id)->first();
        $date = $invoice->issue_date ?? $invoice->invoice_date ?? date('Y-m-d');
        $companyId = DB::table('departments')->where('id', $invoice->department_id)->value('company_id');
        if ($invoice->status === 'cancelled' && $entry && $entry->status === 'posted') {
            LedgerPostingService::postJournal($date, 'Cancelled invoice '.$invoice->invoice_no, $entry->lines->map(fn ($line) => ['account_id' => $line->account_id, 'currency' => $line->currency, 'debit' => $line->credit, 'credit' => $line->debit])->all(), $entry->id, 'Reversal', $companyId);
            $entry->update(['status' => 'reversed']);

            return;
        }
        if ($entry || in_array($invoice->status, ['draft', 'pending_approval', 'rejected', 'cancelled']) || $invoice->amount <= 0) {
            return;
        }
        $tax = (float) ($invoice->tax_amount ?? 0);
        $lines = [['account_code' => '1200', 'debit' => $invoice->amount, 'currency' => $invoice->currency], ['account_code' => '4100', 'credit' => $invoice->amount - $tax, 'currency' => $invoice->currency]];
        if ($tax > 0) {
            $lines[] = ['account_code' => '2250', 'account_name' => 'Sales tax payable', 'account_type' => 'liability', 'credit' => $tax, 'currency' => $invoice->currency];
        }
        LedgerPostingService::postJournal($date, 'Invoice '.$invoice->invoice_no, $lines, $id, 'Invoice', $companyId);
    }
}
