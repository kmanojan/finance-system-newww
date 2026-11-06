<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LoanBalanceService
{
    public const INTEREST_SEPARATE = 'interest_separate';

    public const PRINCIPAL_AND_INTEREST = 'principal_and_interest';

    public static function type(object $loan): string
    {
        return ($loan->loan_type ?? self::INTEREST_SEPARATE) === self::PRINCIPAL_AND_INTEREST
            ? self::PRINCIPAL_AND_INTEREST : self::INTEREST_SEPARATE;
    }

    public static function label(string $type): string
    {
        return $type === self::PRINCIPAL_AND_INTEREST ? 'Type 2 · Principal + interest' : 'Type 1 · Interest paid separately';
    }

    public static function calculate(object $loan, ?Collection $schedules = null, ?Collection $records = null): array
    {
        $schedules ??= DB::table('loan_interest_schedule')->where('loan_id', $loan->id)->get();
        $records ??= DB::table('loan_principal_records')->where('loan_id', $loan->id)->get();
        $cents = fn ($amount) => (int) round((float) ($amount ?? 0) * 100);
        $draws = $records->where('record_type', 'draw')->sum(fn ($row) => $cents($row->amount));
        $repaid = $records->where('record_type', 'repayment')->sum(fn ($row) => $cents($row->amount));
        $borrowed = $cents($loan->principal_amount) + $draws;
        $principal = max(0, $borrowed - $repaid);
        // Never infer payments from a status: only recorded settlements count as paid.
        $interestPaid = $schedules->sum(fn ($row) => $cents($row->paid_amount));
        $interest = $schedules->whereIn('status', ['pending', 'partially_paid', 'overdue'])
            ->sum(fn ($row) => max(0, $cents($row->interest_amount) - $cents($row->paid_amount)));
        $contracted = $schedules->sum(fn ($row) => $cents($row->interest_amount));
        if ($schedules->isEmpty() && in_array($loan->status, ['pending', 'active', 'defaulted'])) {
            $term = max(1, (int) ($loan->term_months ?? 1));
            $periodic = match ($loan->interest_method) {
                'fixed_amount' => $cents($loan->interest_amount ?? 0),
                'percentage_rate' => $cents((float) $loan->principal_amount * (float) ($loan->interest_rate ?? 0) / 100),
                default => 0,
            };
            $contracted = in_array($loan->interest_method, ['total_interest', 'equal_installments'])
                ? $cents($loan->total_interest ?? 0) : $periodic * $term;
            if (! empty($loan->is_upfront_interest) && $periodic > 0) {
                $contracted = $cents(($loan->upfront_interest_amount ?? null) ?: $periodic / 100) + $periodic * ($term - 1);
            }
            $interest = $contracted;
        }
        $type = self::type($loan);
        $includeInterest = $type === self::PRINCIPAL_AND_INTEREST;
        $payable = $principal + ($includeInterest ? $interest : 0);
        $obligation = $borrowed + ($includeInterest ? $contracted : 0);
        $paidTowardsPayable = $repaid + ($includeInterest ? $interestPaid : 0);

        return [
            'loan_type' => $type,
            'loan_type_label' => self::label($type),
            'total_borrowed' => $borrowed / 100,
            'principal_repaid' => $repaid / 100,
            'outstanding_principal' => $principal / 100,
            'interest_paid' => $interestPaid / 100,
            'pending_interest' => $interest / 100,
            'contracted_interest' => $contracted / 100,
            'interest_included' => $includeInterest,
            'total_outstanding' => $payable / 100,
            'total_obligation' => $obligation / 100,
            'total_paid' => ($repaid + $interestPaid) / 100,
            'paid_towards_payable' => $paidTowardsPayable / 100,
            'repayment_percent' => $obligation > 0 ? min(100, round($paidTowardsPayable / $obligation * 100, 1)) : 0,
            // Full closure still settles both principal and interest, regardless of display type.
            'full_settlement_amount' => ($principal + $interest) / 100,
        ];
    }
}
