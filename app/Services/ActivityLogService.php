<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Auth;

class ActivityLogService
{
    /**
     * Ensure activity_logs table exists
     */
    public static function ensureTableExists(): void
    {
        if (!Schema::hasTable('activity_logs')) {
            Schema::create('activity_logs', function ($table) {
                $table->id();
                $table->integer('model_id')->nullable();
                $table->string('model_type', 255)->nullable();
                $table->string('action', 255);
                $table->json('old_value')->nullable();
                $table->json('new_value')->nullable();
                $table->integer('user_id')->nullable();
                $table->timestamps();
            });
        } else {
            if (!Schema::hasColumn('activity_logs', 'updated_at')) {
                Schema::table('activity_logs', function ($table) {
                    $table->timestamp('updated_at')->nullable();
                });
            }
        }
    }

    /**
     * Record a generic activity log entry
     */
    public static function log(string $action, ?string $modelType = null, ?int $modelId = null, mixed $oldValue = null, mixed $newValue = null): void
    {
        try {
            self::ensureTableExists();

            $insertData = [
                'action' => $action,
                'model_type' => $modelType,
                'model_id' => $modelId,
                'old_value' => $oldValue ? (is_string($oldValue) ? $oldValue : json_encode($oldValue)) : null,
                'new_value' => $newValue ? (is_string($newValue) ? $newValue : json_encode($newValue)) : null,
                'user_id' => Auth::id() ?? 1,
                'created_at' => now(),
            ];

            if (Schema::hasColumn('activity_logs', 'updated_at')) {
                $insertData['updated_at'] = now();
            }

            DB::table('activity_logs')->insert($insertData);
        } catch (\Exception $e) {
            logger()->error('ActivityLogService Error: ' . $e->getMessage());
        }
    }


    /**
     * Convenience method for store / create operations
     */
    public static function logCreate(string $modelType, int|string|null $modelId, mixed $data, ?string $customAction = null): void
    {
        $action = $customAction ?? ("Created " . class_basename($modelType) . ($modelId ? " #" . $modelId : ''));
        self::log($action, class_basename($modelType), $modelId ? (int)$modelId : null, null, $data);
    }

    /**
     * Convenience method for update operations
     */
    public static function logUpdate(string $modelType, int|string|null $modelId, mixed $oldData, mixed $newData, ?string $customAction = null): void
    {
        $action = $customAction ?? ("Updated " . class_basename($modelType) . ($modelId ? " #" . $modelId : ''));

        // Convert objects to arrays
        $oldArr = is_object($oldData) ? (array)$oldData : (is_array($oldData) ? $oldData : []);
        $newArr = is_object($newData) ? (array)$newData : (is_array($newData) ? $newData : []);

        // Ignore structural/metadata keys
        $ignoredKeys = ['id', 'created_at', 'updated_at', 'deleted_at', '_token', '_method'];
        foreach ($ignoredKeys as $k) {
            unset($oldArr[$k], $newArr[$k]);
        }

        // Only compare keys submitted in the update payload
        if (!empty($newArr)) {
            $filteredOld = [];
            foreach ($newArr as $k => $v) {
                if (array_key_exists($k, $oldArr)) {
                    $filteredOld[$k] = $oldArr[$k];
                }
            }
            $oldArr = $filteredOld;
        }

        self::log($action, class_basename($modelType), $modelId ? (int)$modelId : null, $oldArr, $newArr);
    }


    /**
     * Convenience method for delete / destroy operations
     */
    public static function logDelete(string $modelType, int|string|null $modelId, mixed $oldData = null, ?string $customAction = null): void
    {
        $action = $customAction ?? ("Deleted " . class_basename($modelType) . ($modelId ? " #" . $modelId : ''));
        self::log($action, class_basename($modelType), $modelId ? (int)$modelId : null, $oldData, null);
    }
}
