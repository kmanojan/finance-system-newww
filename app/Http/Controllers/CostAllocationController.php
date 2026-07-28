<?php

namespace App\Http\Controllers;

use App\Models\CostAllocation;
use Illuminate\Http\Request;

class CostAllocationController extends Controller
{
    public function index(Request $request)
    {
        $allocations = CostAllocation::with(['employee', 'server'])
            ->when($request->project_id, fn ($q) => $q->where('project_id', $request->project_id))
            ->when($request->type, fn ($q) => $q->where('type', $request->type))
            ->when($request->from && $request->to, fn ($q) => $q->whereBetween('period_start', [$request->from, $request->to]))
            ->latest('period_start')
            ->paginate(25);

        return response()->json($allocations);
    }

    public function store(Request $request)
    {
        if ($request->has('allocations') && is_array($request->allocations)) {
            $validated = $request->validate([
                'project_id'                     => 'required|exists:projects,id',
                'type'                           => 'required|in:employee,server,other',
                'currency'                       => 'required|string|size:3',
                'period_start'                   => 'required|date',
                'period_end'                     => 'nullable|date',
                'allocations'                    => 'required|array|min:1',
                'allocations.*.employee_id'      => 'nullable|exists:employees,id',
                'allocations.*.server_id'        => 'nullable|exists:servers,id',
                'allocations.*.cost_center_name' => 'nullable|string',
                'allocations.*.amount'           => 'required|numeric|min:0',
                'allocations.*.notes'            => 'nullable|string',
            ]);

            $created = [];
            foreach ($validated['allocations'] as $row) {
                $created[] = CostAllocation::create([
                    'project_id'       => $validated['project_id'],
                    'type'             => $validated['type'],
                    'employee_id'      => $row['employee_id'] ?? null,
                    'server_id'        => $row['server_id'] ?? null,
                    'cost_center_name' => $row['cost_center_name'] ?? null,
                    'period_start'     => $validated['period_start'],
                    'period_end'       => $validated['period_end'] ?? null,
                    'amount'           => $row['amount'],
                    'currency'         => $validated['currency'],
                    'notes'            => $row['notes'] ?? null,
                    'source'           => 'manual',
                    'created_by'       => auth()->id() ?? 1,
                ]);
            }
            return response()->json($created, 201);
        }

        $validated = $request->validate([
            'project_id'       => 'required|exists:projects,id',
            'type'             => 'required|in:employee,server,other',
            'employee_id'      => 'nullable|exists:employees,id',
            'server_id'        => 'required_if:type,server|nullable|exists:servers,id',
            'cost_center_name' => 'required_if:type,other|nullable|string',
            'period_start'     => 'required|date',
            'period_end'       => 'nullable|date|after_or_equal:period_start',
            'amount'           => 'required|numeric|min:0',
            'currency'         => 'required|string|size:3',
            'notes'            => 'nullable|string',
        ]);

        $allocation = CostAllocation::create([
            ...$validated,
            'source'     => 'manual',
            'created_by' => auth()->id() ?? 1,
        ]);

        return response()->json($allocation->load(['employee', 'server']), 201);
    }

    public function destroy(CostAllocation $costAllocation)
    {
        $costAllocation->delete();
        return response()->noContent();
    }
}
