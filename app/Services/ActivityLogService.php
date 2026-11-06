<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ActivityLogService
{
    /**
     * Record a generic activity log entry
     */
    public static function log(string $action, ?string $modelType = null, ?int $modelId = null, mixed $oldValue = null, mixed $newValue = null): void
    {
        $entry = [
            'action' => $action, 'model_type' => $modelType, 'model_id' => $modelId,
            'old_value' => $oldValue === null ? null : json_encode(self::redact($oldValue)),
            'new_value' => $newValue === null ? null : json_encode(self::redact($newValue)),
            'user_id' => Auth::id(),
            'created_at' => now(), 'updated_at' => now(),
        ];
        if (Schema::hasColumn('activity_logs', 'ip_address')) {
            $entry['ip_address'] = request()->ip();
        }
        DB::table('activity_logs')->insert($entry);
    }

    private static function redact(mixed $value): mixed
    {
        if (is_object($value)) {
            $value = (array) $value;
        }
        if (! is_array($value)) {
            return $value;
        }
        foreach ($value as $key => &$item) {
            if (preg_match('/password|token|secret/i', (string) $key)) {
                $item = '[redacted]';
            } else {
                $item = self::redact($item);
            }
        }

        return $value;
    }

    /**
     * Convenience method for store / create operations
     */
    public static function logCreate(string $modelType, int|string|null $modelId, mixed $data, ?string $customAction = null): void
    {
        $action = $customAction ?? ('Created '.class_basename($modelType).($modelId ? ' #'.$modelId : ''));
        self::log($action, class_basename($modelType), $modelId ? (int) $modelId : null, null, $data);
    }

    /**
     * Convenience method for update operations
     */
    public static function logUpdate(string $modelType, int|string|null $modelId, mixed $oldData, mixed $newData, ?string $customAction = null): void
    {
        $action = $customAction ?? ('Updated '.class_basename($modelType).($modelId ? ' #'.$modelId : ''));

        // Convert objects to arrays
        $oldArr = is_object($oldData) ? (array) $oldData : (is_array($oldData) ? $oldData : []);
        $newArr = is_object($newData) ? (array) $newData : (is_array($newData) ? $newData : []);

        // Ignore structural/metadata keys
        $ignoredKeys = ['id', 'created_at', 'updated_at', 'deleted_at', '_token', '_method'];
        foreach ($ignoredKeys as $k) {
            unset($oldArr[$k], $newArr[$k]);
        }

        // Only compare keys submitted in the update payload
        if (! empty($newArr)) {
            $filteredOld = [];
            foreach ($newArr as $k => $v) {
                if (array_key_exists($k, $oldArr)) {
                    $filteredOld[$k] = $oldArr[$k];
                }
            }
            $oldArr = $filteredOld;
        }

        self::log($action, class_basename($modelType), $modelId ? (int) $modelId : null, $oldArr, $newArr);
    }

    /**
     * Convenience method for delete / destroy operations
     */
    public static function logDelete(string $modelType, int|string|null $modelId, mixed $oldData = null, ?string $customAction = null): void
    {
        $action = $customAction ?? ('Deleted '.class_basename($modelType).($modelId ? ' #'.$modelId : ''));
        self::log($action, class_basename($modelType), $modelId ? (int) $modelId : null, $oldData, null);
    }
}
