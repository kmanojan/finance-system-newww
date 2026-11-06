<?php

namespace App\Http\Controllers;

use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {

        $query = DB::table('activity_logs');

        if ($request->filled('action')) {
            $query->where('action', 'LIKE', '%'.$request->query('action').'%');
        }

        if ($request->filled('model_type')) {
            $query->where('model_type', 'LIKE', '%'.$request->query('model_type').'%');
        }

        $logs = $query->orderBy('id', 'desc')->paginate(30);

        return view('activity-logs', compact('logs'));
    }

    public static function log($action, $modelType = null, $modelId = null, $oldValue = null, $newValue = null)
    {
        ActivityLogService::log($action, $modelType, $modelId, $oldValue, $newValue);
    }
}
