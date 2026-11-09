<?php

namespace App\Http\Controllers;

use App\Models\ApiIntegration;
use App\Models\Employee;
use App\Models\EmployeeSalaryComponent;
use App\Models\SalaryComponent;
use App\Services\ActivityLogService;
use App\Services\EmployeePartyService;
use App\Services\SalaryHistoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $employees = $this->employeeQuery($request)->orderBy('full_name')->orderBy('id')->paginate(20)->withQueryString();

        return response()->json($employees);
    }

    public function webIndex(Request $request)
    {
        $employees = $this->employeeQuery($request)->orderBy('full_name')->orderBy('id')->paginate(20)->withQueryString();
        $integration = ApiIntegration::first();
        $activeEmployees = Employee::active()->with(['salaryComponents.component'])->get();
        $amounts = ['basic_salary', 'gross_salary', 'net_salary', 'total_employer_cost', 'ctc'];
        $salaryTotals = [];
        foreach ($activeEmployees as $employee) {
            $salary = $employee->salary_breakdown;
            $currency = $salary['currency'];
            foreach ($amounts as $amount) {
                $salaryTotals[$currency][$amount] = ($salaryTotals[$currency][$amount] ?? 0) + (int) round($salary[$amount] * 100);
            }
        }
        ksort($salaryTotals);
        $activeCount = $activeEmployees->count();

        return view('employees.index', compact('employees', 'integration', 'salaryTotals', 'activeCount'));
    }

    private function employeeQuery(Request $request)
    {
        $data = $request->validate(['search' => 'nullable|string|max:200', 'status' => 'nullable|in:active,inactive']);
        $search = preg_replace('/\s+/u', ' ', trim($data['search'] ?? ''));

        return Employee::query()->with(['salaryComponents.component'])
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->whereLike('full_name', "%{$search}%")
                ->orWhereLike('employee_code', "%{$search}%")
                ->orWhereLike('job_position', "%{$search}%")
                ->orWhereLike('role', "%{$search}%")))
            ->when(! empty($data['status']), fn ($query) => $query->where('status', $data['status']));
    }

    public function create()
    {
        return view('employees.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'first_name' => 'required|string|max:100', 'last_name' => 'nullable|string|max:100',
            'employee_code' => ['nullable', 'string', 'max:50', function ($attribute, $value, $fail) {
                if (Employee::whereRaw('LOWER(employee_code) = ?', [mb_strtolower($value)])->exists()) {
                    $fail('This employee code is already in use.');
                }
            }],
            'personal_email' => 'nullable|email|max:255', 'mobile_phone' => 'nullable|string|max:50',
            'job_position' => 'nullable|string|max:255', 'role' => 'nullable|string|max:255',
            'status' => 'required|in:active,inactive',
        ]);
        $data['first_name'] = trim($data['first_name']);
        $data['last_name'] = trim($data['last_name'] ?? '');
        $data['full_name'] = trim($data['first_name'].' '.$data['last_name']);
        // Never allocate a synthetic Nexus ID or accept source IDs from the browser.
        $employee = Employee::create($data + ['external_id' => null]);
        if (blank($employee->employee_code)) {
            $code = 'MAN-'.str_pad((string) $employee->id, 6, '0', STR_PAD_LEFT);
            while (Employee::whereRaw('LOWER(employee_code) = ?', [strtolower($code)])->exists()) {
                $code .= '-'.strtolower(Str::random(4));
            }
            $employee->update(['employee_code' => $code]);
        }
        ActivityLogService::logCreate('Employee', $employee->id, $employee->only(['full_name', 'employee_code', 'status', 'job_position', 'role']));

        return redirect()->route('employees.show', $employee->id)->with('success', 'Employee added and linked as a party. Configure their salary and components below.');
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
