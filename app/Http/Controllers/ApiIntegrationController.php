<?php

namespace App\Http\Controllers;

use App\Jobs\SyncEmployeesJob;
use App\Models\ApiIntegration;
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
            'name' => 'required|string',
            'url' => 'required|url',
            'method' => 'required|in:GET,POST',
            'bearer_token' => $existing ? 'nullable|string' : 'required|string',
            'response_path' => 'nullable|string',
        ]);

        if (empty($validated['bearer_token']) && $existing) {
            $validated['bearer_token'] = $existing->bearer_token;
        }
        $validated['bearer_token'] = preg_replace('/^Bearer\s+/i', '', trim($validated['bearer_token'] ?? ''));
        if (parse_url($validated['url'], PHP_URL_HOST) === 'nexus-api.apptimus.io') {
            $parts = explode('?', explode('#', $validated['url'], 2)[0], 2);
            parse_str($parts[1] ?? '', $parameters);
            unset($parameters['status']);
            $validated['url'] = $parts[0].($parameters ? '?'.http_build_query($parameters) : '');
        }

        $integration = ApiIntegration::updateOrCreate(
            ['id' => $existing?->id],
            $validated
        );

        return response()->json($integration, 201);
    }

    public function sync(ApiIntegration $apiIntegration)
    {
        try {
            $count = (new SyncEmployeesJob($apiIntegration))->handle();
        } catch (\Throwable $error) {
            $apiIntegration->refresh();

            return response()->json(['status' => 'failed', 'error' => $apiIntegration->last_sync_error ?: 'Employee synchronization failed. Check the integration settings and retry.']);
        }
        $apiIntegration->refresh();

        return response()->json([
            'status' => $apiIntegration->last_sync_status,
            'error' => $apiIntegration->last_sync_error,
            'synced_at' => $apiIntegration->last_synced_at,
            'employee_count' => $count,
        ]);
    }
}
