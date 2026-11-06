<?php

namespace App\Services;

use App\Models\Employee;
use Illuminate\Support\Facades\DB;

class EmployeePartyService
{
    /** The employee ID is the identity key; names alone never merge financial parties. */
    public static function sync(Employee $employee): int
    {
        return DB::transaction(function () use ($employee) {
            $record = DB::table('employees')->where('id', $employee->id)->lockForUpdate()->firstOrFail();
            $identity = [
                'name' => $record->full_name,
                'email' => $record->personal_email,
                'phone' => $record->mobile_phone,
            ];
            if ($record->party_id) {
                $party = DB::table('parties')->where('id', $record->party_id)->lockForUpdate()->firstOrFail();
                $roles = array_filter(array_map('trim', explode(',', $party->types ?? '')));
                $identity['types'] = implode(',', array_unique([...$roles, 'employee']));
                $changes = array_filter($identity, fn ($value, $key) => $value !== $party->$key, ARRAY_FILTER_USE_BOTH);
                if ($changes) {
                    DB::table('parties')->where('id', $party->id)->update($changes + ['updated_at' => now()]);
                    ActivityLogService::logUpdate('Party', $party->id, (array) $party, $changes);
                }
                $partyId = $party->id;
            } else {
                // Keep inactive employees available for outstanding payables; party status is managed separately.
                $data = $identity + ['types' => 'employee', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()];
                $partyId = DB::table('parties')->insertGetId($data);
                DB::table('employees')->where('id', $record->id)->update(['party_id' => $partyId]);
                ActivityLogService::logCreate('Party', $partyId, $identity + ['types' => 'employee', 'employee_id' => $record->id]);
            }
            $employee->party_id = $partyId;
            $employee->syncOriginalAttribute('party_id');
            $employee->unsetRelation('party');

            return $partyId;
        });
    }
}
