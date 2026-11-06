<?php

namespace App\Http\Controllers;

use App\Models\FinancialYear;
use App\Models\FiscalPeriod;
use App\Services\ActivityLogService;
use App\Services\YearEndCloseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FinancialYearController extends Controller
{
    public function index(Request $request)
    {
        $years = FinancialYear::with('periods')->orderBy('start_date', 'desc')->get();
        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'data' => $years]);
        }

        return view('settings.financial-years', ['years' => $years, 'companies' => DB::table('companies')->get()]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'title' => 'required|string|max:100',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
        ]);

        if (FinancialYear::where('company_id', $validated['company_id'])->whereDate('start_date', '<=', $validated['end_date'])->whereDate('end_date', '>=', $validated['start_date'])->exists()) {
            throw ValidationException::withMessages(['start_date' => 'Financial years cannot overlap for the same company.']);
        }

        return DB::transaction(function () use ($validated, $request) {
            $fy = FinancialYear::create($validated);

            // Generate 12 monthly periods automatically
            $start = new \DateTime($validated['start_date']);
            $end = new \DateTime($validated['end_date']);

            $current = clone $start;
            while ($current <= $end) {
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

            if (! $request->expectsJson()) {
                return back()->with('success', 'Financial year and periods created.');
            }

            return response()->json([
                'success' => true,
                'message' => 'Financial year and monthly fiscal periods created.',
                'data' => $fy->load('periods'),
            ], 201);
        });
    }

    public function updatePeriodStatus(Request $request, $periodId)
    {
        $validated = $request->validate([
            'status' => 'required|in:open,soft_closed,hard_closed',
        ]);

        $period = FiscalPeriod::findOrFail($periodId);
        if ($period->financialYear->is_closed) {
            throw ValidationException::withMessages(['status' => 'Periods in a closed financial year cannot be reopened.']);
        }
        $oldStatus = $period->status;
        $period->update([
            'status' => $validated['status'],
            'closed_at' => $validated['status'] === 'hard_closed' ? now() : null,
            'closed_by' => $validated['status'] === 'hard_closed' ? auth()->id() : null,
        ]);

        ActivityLogService::logUpdate('FiscalPeriod', $period->id, ['status' => $oldStatus], ['status' => $period->status]);
        if (! $request->expectsJson()) {
            return back()->with('success', 'Fiscal period updated.');
        }

        return response()->json([
            'success' => true,
            'message' => "Fiscal period '{$period->period_name}' status updated to {$period->status}.",
            'data' => $period,
        ]);
    }

    public function close(Request $request, int $id)
    {
        YearEndCloseService::close($id);

        return $request->expectsJson() ? response()->json(['success' => true]) : back()->with('success', 'Financial year closed. Revenue and expense balances transferred to retained earnings.');
    }
}
