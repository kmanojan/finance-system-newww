<?php

namespace App\Http\Controllers;

use App\Models\ApiIntegration;
use App\Jobs\SyncEmployeesJob;
use Illuminate\Http\Request;

class ApiIntegrationController extends Controller
{
    public function store(Request $request)
    {
        $existing = null;
        if ($request->filled('id')) {
            $existing = ApiIntegration::find($request->id);
        } elseif ($request->filled('name')) {
            $existing = ApiIntegration::where('name', $request->name)->first();
        }

        $validated = $request->validate([
            'name'          => 'required|string',
            'url'           => 'required|url',
            'method'        => 'required|in:GET,POST',
            'bearer_token'  => $existing ? 'nullable|string' : 'required|string',
            'response_path' => 'nullable|string',
        ]);

        if (empty($validated['bearer_token']) && $existing) {
            $validated['bearer_token'] = $existing->bearer_token;
        }

        $integration = ApiIntegration::updateOrCreate(
            ['name' => $validated['name']],
            $validated
        );

        return response()->json($integration, 201);
    }

    public function sync(ApiIntegration $apiIntegration)
    {
        SyncEmployeesJob::dispatchSync($apiIntegration);
        $apiIntegration->refresh();

        return response()->json([
            'status'    => $apiIntegration->last_sync_status,
            'error'     => $apiIntegration->last_sync_error,
            'synced_at' => $apiIntegration->last_synced_at,
        ]);
    }
}
