<?php

namespace App\Http\Controllers;

use App\Models\FinancialYear;
use App\Models\FiscalPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FinancialYearController extends Controller
{
    public function index()
    {
        $years = FinancialYear::with('periods')->orderBy('start_date', 'desc')->get();
        return response()->json(['success' => true, 'data' => $years]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'title' => 'required|string|max:100',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
        ]);

        return DB::transaction(function () use ($validated) {
            $fy = FinancialYear::create($validated);

            // Generate 12 monthly periods automatically
            $start = new \DateTime($validated['start_date']);
            $end = new \DateTime($validated['end_date']);
            
            $current = clone $start;
            while ($current < $end) {
                $periodStart = clone $current;
                $periodEnd = (clone $current)->modify('last day of this month');
                if ($periodEnd > $end) {
                    $periodEnd = clone $end;
                }

                FiscalPeriod::create([
                    'financial_year_id' => $fy->id,
                    'period_name' => $periodStart->format('F Y'),
                    'start_date' => $periodStart->format('Y-m-d'),
                    'end_date' => $periodEnd->format('Y-m-d'),
                    'status' => 'open',
                ]);

                $current->modify('first day of next month');
            }

            return response()->json([
                'success' => true,
                'message' => 'Financial year and monthly fiscal periods created.',
                'data' => $fy->load('periods')
            ], 201);
        });
    }

    public function updatePeriodStatus(Request $request, $periodId)
    {
        $validated = $request->validate([
            'status' => 'required|in:open,soft_closed,hard_closed',
        ]);

        $period = FiscalPeriod::findOrFail($periodId);
        $period->update([
            'status' => $validated['status'],
            'closed_at' => $validated['status'] === 'hard_closed' ? now() : null,
            'closed_by' => $validated['status'] === 'hard_closed' ? auth()->id() : null,
        ]);

        return response()->json([
            'success' => true,
            'message' => "Fiscal period '{$period->period_name}' status updated to {$period->status}.",
            'data' => $period
        ]);
    }
}
