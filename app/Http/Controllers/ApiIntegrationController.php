<?php

namespace App\Http\Controllers;

use App\Models\ApiIntegration;
use App\Jobs\SyncEmployeesJob;
use Illuminate\Http\Request;

class ApiIntegrationController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'          => 'required|string',
            'url'           => 'required|url',
            'method'        => 'required|in:GET,POST',
            'bearer_token'  => 'required|string',
            'response_path' => 'nullable|string',
        ]);

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
