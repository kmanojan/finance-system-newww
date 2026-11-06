<?php

namespace App\Http\Controllers;

use App\Models\ApiIntegration;
use App\Models\Employee;
use App\Models\EmployeeSalaryComponent;
use App\Models\SalaryComponent;
use App\Services\EmployeePartyService;
use App\Services\SalaryHistoryService;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $employees = Employee::query()
            ->with(['salaryComponents.component'])
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->search, fn ($q) => $q->where(function ($q) use ($request) {
                $q->where('full_name', 'like', "%{$request->search}%")
                    ->orWhere('employee_code', 'like', "%{$request->search}%");
            }))
            ->orderBy('full_name')
            ->paginate(20);

        return response()->json($employees);
    }

    public function webIndex(Request $request)
    {
        $query = Employee::query()->with(['salaryComponents.component']);

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('full_name', 'like', "%{$request->search}%")
                    ->orWhere('employee_code', 'like', "%{$request->search}%")
                    ->orWhere('job_position', 'like', "%{$request->search}%");
            });
        }

        if ($request->status) {
            $query->where('status', $request->status);
        }

        $employees = $query->orderBy('full_name')->paginate(20);
        $integration = ApiIntegration::first();
        $salaryComponents = SalaryComponent::active()->orderBy('sort_order')->get();

        // Calculate KPI rollups across all active employees
        $allActiveEmployees = Employee::active()->with(['salaryComponents.component'])->get();
        $totalBasicPayroll = 0.0;
        $totalGrossPayroll = 0.0;
        $totalNetPayable = 0.0;
        $totalEmployerEpfEtf = 0.0;
        $totalCtc = 0.0;

        foreach ($allActiveEmployees as $emp) {
            $b = $emp->salary_breakdown;
            $totalBasicPayroll += $b['basic_salary'];
            $totalGrossPayroll += $b['gross_salary'];
            $totalNetPayable += $b['net_salary'];
            $totalEmployerEpfEtf += $b['total_employer_cost'];
            $totalCtc += $b['ctc'];
        }

        $kpis = [
            'total_employees' => $allActiveEmployees->count(),
            'total_basic_payroll' => $totalBasicPayroll,
            'total_gross_payroll' => $totalGrossPayroll,
            'total_net_payable' => $totalNetPayable,
            'total_employer_epf_etf' => $totalEmployerEpfEtf,
            'total_ctc' => $totalCtc,
        ];

        return view('employees.index', compact('employees', 'integration', 'salaryComponents', 'kpis'));
    }

    public function show($id)
    {
        $employee = Employee::with(['salaryComponents.component', 'costAllocations.project'])->findOrFail($id);
        $allComponents = SalaryComponent::active()->orderBy('sort_order')->get();

        // Defaults are previewed here and only assigned when the user saves.
        $salaryBreakdown = $employee->salary_breakdown;

        $componentsData = $allComponents->map(function ($c) use ($employee) {
            $mapping = $employee->salaryComponents->firstWhere('salary_component_id', $c->id);

            return [
                'id' => $c->id,
                'name' => $c->name,
                'code' => $c->code,
                'type' => $c->type,
                'calc_type' => $c->calculation_type,
                'calc_base' => $c->calculation_base,
                'default_val' => (float) $c->default_value,
                'value' => (float) ($mapping ? $mapping->value : $c->default_value),
                'enabled' => $mapping ? (bool) $mapping->is_enabled : (bool) $c->is_statutory,
                'is_epf_eligible' => (bool) $c->is_epf_eligible,
                'is_statutory' => (bool) $c->is_statutory,
            ];
        })->values()->toArray();

        $salaryHistory = SalaryHistoryService::query($employee->id)->orderByDesc('id')->paginate(10, ['*'], 'salary_history_page')->withQueryString()->fragment('salary-history');
        $salaryHistory->through(fn ($row) => SalaryHistoryService::present($row));
        $salaryChart = SalaryHistoryService::query($employee->id)->orderByDesc('id')->limit(100)->get()->reverse()->map(fn ($row) => SalaryHistoryService::present($row))->values();
        $salaryCurrent = SalaryHistoryService::snapshot($employee);

        return view('employees.show', compact('employee', 'allComponents', 'salaryBreakdown', 'componentsData', 'salaryHistory', 'salaryChart', 'salaryCurrent'));
    }

    public function syncParty($id)
    {
        $employee = Employee::findOrFail($id);
        if ($employee->party_id) {
            return back()->with('success', "{$employee->full_name} is already linked to a party.");
        }

        EmployeePartyService::sync($employee);

        return back()->with('success', "{$employee->full_name} synced as a party.");
    }

    public function updateSalary(Request $request, $id)
    {
        $employee = Employee::lockForUpdate()->findOrFail($id);

        $validated = $request->validate([
            'basic_salary' => 'required|numeric|min:0|max:9999999999.99|decimal:0,2',
            'salary_change_reason' => 'nullable|string|max:1000',
            'currency' => 'required|string|size:3',
            'payment_mode' => 'required|string|in:bank_transfer,cash,cheque',
            'bank_name' => 'nullable|string|max:100',
            'bank_account_no' => 'nullable|string|max:50',
            'bank_branch' => 'nullable|string|max:100',
            'epf_number' => 'nullable|string|max:50',
            'components' => 'nullable|array',
            'components.*.id' => 'required|exists:salary_components,id',
            'components.*.value' => 'nullable|numeric|min:0',
            'components.*.enabled' => 'nullable',
        ]);

        $salaryBefore = SalaryHistoryService::snapshot($employee);

        $employee->update([
            'basic_salary' => $validated['basic_salary'],
            'currency' => $validated['currency'],
            'payment_mode' => $validated['payment_mode'],
            'bank_name' => $validated['bank_name'] ?? null,
            'bank_account_no' => $validated['bank_account_no'] ?? null,
            'bank_branch' => $validated['bank_branch'] ?? null,
            'epf_number' => $validated['epf_number'] ?? null,
        ]);

        // Process components mapping
        if (! empty($validated['components'])) {
            foreach ($validated['components'] as $compData) {
                $val = isset($compData['value']) && is_numeric($compData['value']) ? (float) $compData['value'] : 0.0;
                $isEnabled = ! empty($compData['enabled']) && $compData['enabled'] !== '0' && $compData['enabled'] !== 0;

                EmployeeSalaryComponent::updateOrCreate(
                    [
                        'employee_id' => $employee->id,
                        'salary_component_id' => $compData['id'],
                    ],
                    [
                        'value' => $val,
                        'is_enabled' => $isEnabled,
                    ]
                );
            }
        }

        SalaryHistoryService::record($employee, $salaryBefore, $validated['salary_change_reason'] ?? null);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Salary profile updated for {$employee->full_name}.",
                'breakdown' => $employee->fresh()->salary_breakdown,
            ]);
        }

        return redirect()->back()->with('success', "Salary configuration updated successfully for {$employee->full_name}.");
    }

    public function salaryPreview(Request $request, $id)
    {
        $employee = Employee::findOrFail($id);

        return response()->json($employee->salary_breakdown);
    }
}
