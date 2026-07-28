<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ActivityLogController extends Controller
{
    private function ensureTableExists()
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
        }
    }

    public function index(Request $request)
    {
        $this->ensureTableExists();

        $query = DB::table('activity_logs');

        if ($request->filled('action')) {
            $query->where('action', 'LIKE', '%' . $request->query('action') . '%');
        }

        if ($request->filled('model_type')) {
            $query->where('model_type', 'LIKE', '%' . $request->query('model_type') . '%');
        }

        $logs = $query->orderBy('id', 'desc')->paginate(30);

        return view('activity-logs', compact('logs'));
    }

    public static function log($action, $modelType = null, $modelId = null, $oldValue = null, $newValue = null)
    {
        if (!Schema::hasTable('activity_logs')) {
            return;
        }

        DB::table('activity_logs')->insert([
            'action' => $action,
            'model_type' => $modelType,
            'model_id' => $modelId,
            'old_value' => $oldValue ? json_encode($oldValue) : null,
            'new_value' => $newValue ? json_encode($newValue) : null,
            'user_id' => auth()->id() ?? 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
