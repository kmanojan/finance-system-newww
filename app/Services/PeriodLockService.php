<?php

namespace App\Services;

use App\Models\FiscalPeriod;
use Exception;

class PeriodLockService
{
    /**
     * Checks if a date falls in a hard_closed fiscal period.
     * Throws Exception if locked.
     */
    public static function checkLockedDate(string $date): void
    {
        $formattedDate = date('Y-m-d', strtotime($date));

        $period = FiscalPeriod::where('start_date', '<=', $formattedDate)
            ->where('end_date', '>=', $formattedDate)
            ->where('status', 'hard_closed')
            ->first();

        if ($period) {
            throw new Exception("Period '{$period->period_name}' is hard-closed. Transactions cannot be created, updated, or deleted for date {$formattedDate}.");
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
