<?php

namespace App\Services;

use App\Models\FiscalPeriod;
use Exception;
use Illuminate\Validation\ValidationException;

class PeriodLockService
{
    /**
     * Checks if a date falls in a hard_closed fiscal period.
     * Throws Exception if locked.
     */
    public static function checkLockedDate(string $date, ?int $companyId = null): void
    {
        $formattedDate = date('Y-m-d', strtotime($date));

        $period = FiscalPeriod::whereDate('start_date', '<=', $formattedDate)
            ->whereDate('end_date', '>=', $formattedDate)
            ->when($companyId, fn ($q) => $q->whereHas('financialYear', fn ($year) => $year->where('company_id', $companyId)))
            ->whereIn('status', ['hard_closed', 'soft_closed'])
            ->first();

        if ($period) {
            throw ValidationException::withMessages(['transaction_date' => "Period '{$period->period_name}' is closed. Transactions cannot be created, updated, or deleted for date {$formattedDate}."]);
        }
    }

    /**
     * Returns whether a date is editable.
     */
    public static function isDateEditable(string $date): bool
    {
        try {
            self::checkLockedDate($date);

            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}
