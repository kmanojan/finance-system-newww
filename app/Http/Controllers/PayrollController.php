<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\SalaryComponent;
use App\Services\PayrollCalculationService;
use App\Services\PayrollService;
use App\Services\SalaryAdvanceService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PayrollController extends Controller
{
    public function index(Request $request)
    {
        $runs = DB::table('payroll_runs as r')->join('companies as c', 'c.id', '=', 'r.company_id')
            ->select('r.*', 'c.name as company_name')
            ->when($request->filled('month'), fn ($q) => $q->where('r.month', $request->month.'-01'))
            ->when($request->filled('company_id'), fn ($q) => $q->where('r.company_id', $request->company_id))
            ->orderByDesc('r.month')->orderByDesc('r.id')->paginate(20)->withQueryString();
        foreach ($runs as $r) {
            $items = PayrollService::items($r->id);
            $r->employee_count = $items->count();
            $r->cost = $items->sum('cost_cents');
            $r->balance = collect(PayrollService::balances($r, $items))->sum('balance');
        }

        return view('payroll.index', ['runs' => $runs, 'companies' => DB::table('companies')->orderBy('name')->get()]);
    }

    public function create(Request $request)
    {
        return view('payroll.create', $this->options() + $this->employeeTableData($request));
    }

    public function employees(Request $request)
    {
        if ($request->boolean('select_all')) {
            $ids = $this->employeeQuery($request)->where('status', 'active')->where('currency', 'LKR')->where('basic_salary', '>', 0)->limit(1001)->pluck('id');
            if ($ids->count() > 1000) {
                throw ValidationException::withMessages(['employees' => 'More than 1,000 employees match. Refine your search before selecting all.']);
            }

            return response()->json(['ids' => $ids]);
        }
        $data = $this->employeeTableData($request);

        return response()->json([
            'html' => view('payroll.create-rows', $data)->render(),
            'page' => $data['employees']->currentPage(), 'last_page' => $data['employees']->lastPage(),
            'total' => $data['employees']->total(), 'from' => $data['employees']->firstItem(), 'to' => $data['employees']->lastItem(),
        ]);
    }

    private function employeeQuery(Request $request)
    {
        $request->validate(['search' => 'nullable|string|max:150']);

        return Employee::query()->when($request->filled('search'), function ($q) use ($request) {
            $q->where(fn ($q) => $q->where('full_name', 'like', '%'.$request->search.'%')->orWhere('employee_code', 'like', '%'.$request->search.'%'));
        })->orderBy('full_name')->orderBy('id');
    }

    private function employeeTableData(Request $request): array
    {
        $request->validate(['per_page' => 'sometimes|integer|in:25,50,100', 'page' => 'sometimes|integer|min:1', 'month' => 'nullable|date_format:Y-m', 'company_id' => 'nullable|integer|exists:companies,id']);
        $employees = $this->employeeQuery($request)->with('salaryComponents.component')->paginate((int) $request->input('per_page', 25));
        $components = SalaryComponent::active()->whereNotIn('code', ['BASIC', 'SALARY_ADVANCE'])
            ->whereHas('employeeComponents', fn ($q) => $q->where('is_enabled', true))->orderBy('sort_order')->orderBy('id')->get()->groupBy('type');
        $company = $request->input('company_id', old('company_id', DB::table('companies')->count() === 1 ? DB::table('companies')->value('id') : null));
        $month = $request->input('month', old('month', date('Y-m')));
        $scheduled = SalaryAdvanceService::scheduledTotals($employees->pluck('id')->all(), $company ? (int) $company : null, $month);
        $employeePayroll = [];
        foreach ($employees as $employee) {
            $snapshot = PayrollCalculationService::snapshot($employee);
            try {
                $calculation = SalaryAdvanceService::proposal($snapshot, [], $scheduled[$employee->id] ?? 0)['calculation'];
                $error = null;
            } catch (ValidationException $e) {
                $calculation = null;
                $error = 'Check deductions or enter variable income.';
            }
            $employeePayroll[$employee->id] = compact('snapshot', 'calculation', 'error');
        }

        return compact('employees', 'employeePayroll', 'components');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'company_id' => 'required|exists:companies,id', 'department_id' => 'required|exists:departments,id',
            'month' => 'required|date_format:Y-m', 'currency' => 'required|in:LKR',
            'salary_due_date' => 'required|date_format:Y-m-d', 'statutory_due_date' => 'required|date_format:Y-m-d',
            'tax_due_date' => 'required|date_format:Y-m-d', 'notes' => 'nullable|string|max:2000',
            'employee_ids' => 'required|array|min:1|max:1000|exists:employees,id', 'employee_ids.*' => 'required|integer|distinct',
            'salary_confirmation' => 'accepted',
            'component_amounts' => 'sometimes|array',
            'component_amounts.*' => 'array',
            'component_amounts.*.*' => 'required|numeric|min:0|max:999999999.99|decimal:0,2',
        ]);
        $this->validateComponentEmployees($data);
        $id = PayrollService::create($data);

        if ($request->expectsJson()) {
            return response()->json(['redirect' => '/payroll/'.$id], 201);
        }

        return redirect('/payroll/'.$id)->with('success', 'Payroll created. Review each employee before submitting for approval.');
    }

    public function preview(Request $request)
    {
        $data = $request->validate([
            'month' => 'nullable|date_format:Y-m', 'company_id' => 'nullable|integer|exists:companies,id',
            'employee_ids' => 'present|array|max:1000|exists:employees,id', 'employee_ids.*' => 'integer|distinct',
            'component_amounts' => 'sometimes|array', 'component_amounts.*' => 'array',
            'component_amounts.*.*' => 'required|numeric|min:0|max:999999999.99|decimal:0,2',
        ]);
        $this->validateComponentEmployees($data);
        $scheduled = SalaryAdvanceService::scheduledTotals($data['employee_ids'], ! empty($data['company_id']) ? (int) $data['company_id'] : null, $data['month'] ?? null);
        $result = [];
        foreach (Employee::with('salaryComponents.component')->whereIn('id', $data['employee_ids'])->get() as $employee) {
            abort_unless($employee->currency === 'LKR', 422, 'Select employees with LKR salary profiles.');
            try {
                $result[$employee->id] = SalaryAdvanceService::proposal(PayrollCalculationService::snapshot($employee), ['component_amounts' => $data['component_amounts'][$employee->id] ?? []], $scheduled[$employee->id] ?? 0)['calculation'];
            } catch (ValidationException $error) {
                throw ValidationException::withMessages(['employees' => $employee->full_name.': '.collect($error->errors())->flatten()->first()]);
            }
        }

        return response()->json(['employees' => $result]);
    }

    private function validateComponentEmployees(array $data): void
    {
        if (array_diff(array_map('strval', array_keys($data['component_amounts'] ?? [])), array_map('strval', $data['employee_ids']))) {
            throw ValidationException::withMessages(['component_amounts' => 'Monthly component amounts must belong to selected employees.']);
        }
    }

    public function show(int $id)
    {
        return view('payroll.show', $this->runData($id) + $this->options());
    }

    public function employee(int $id, int $item)
    {
        $data = $this->runData($id);
        $record = $data['items']->firstWhere('id', $item) ?? abort(404);

        return view('payroll.employee', $data + [
            'item' => $record,
            'advanceBalance' => (SalaryAdvanceService::eligible([$record->employee_id], $data['run']->company_id, $data['run']->currency, $data['run']->month)[$record->employee_id] ?? collect())->sum('remaining_cents') + ($data['run']->status === 'approved' ? $record->advance_cents : 0),
            'history' => DB::table('payroll_items as i')->join('payroll_runs as r', 'r.id', '=', 'i.payroll_run_id')->where('i.employee_id', $record->employee_id)->select('i.id', 'r.id as run_id', 'r.month', 'r.status', 'i.net_cents', 'r.currency')->orderByDesc('r.month')->limit(24)->get(),
        ]);
    }

    public function update(Request $request, int $id, int $item)
    {
        $rules = [];
        foreach (['overtime', 'bonus', 'allowance', 'unpaid_leave', 'advance', 'other_deduction', 'epf_base', 'etf_base', 'apit'] as $field) {
            $rules[$field] = 'required|numeric|min:0|max:999999999.99|decimal:0,2';
        }
        $rules += ['component_amounts' => 'sometimes|array', 'component_amounts.*' => 'required|numeric|min:0|max:999999999.99|decimal:0,2', 'reviewed' => 'sometimes|boolean', 'notes' => 'required|string|max:2000'];
        PayrollService::edit($id, $item, $request->validate($rules));

        return redirect('/payroll/'.$id)->with('success', 'Employee payroll saved.');
    }

    public function transition(Request $request, int $id, string $action)
    {
        abort_unless(in_array($action, ['submit', 'return', 'approve', 'void']), 404);
        if (in_array($action, ['approve', 'void'])) {
            abort_unless(in_array(strtolower($request->user()->role), ['admin', 'manager']), 403);
        }
        $data = $action === 'void' ? $request->validate(['reason' => 'required|string|min:5|max:1000', 'date' => 'required|date_format:Y-m-d|before_or_equal:today']) : [];
        PayrollService::transition($id, $action, $data);

        return back()->with('success', 'Payroll updated.');
    }

    public function payment(Request $request, int $id)
    {
        $data = $request->validate($this->paymentRules() + ['kind' => ['required', Rule::in(array_keys(PayrollService::KINDS))], 'item_id' => 'nullable|integer']);
        $payment = PayrollService::payment($id, $data);
        $this->receipt($request, $payment);

        return redirect('/payroll/'.$id.'#payments')->with('success', 'Payment recorded. Outstanding balances have been updated.');
    }

    public function reverse(Request $request, int $payment)
    {
        abort_unless(in_array(strtolower($request->user()->role), ['admin', 'manager']), 403);
        PayrollService::reversePayment($payment, $request->validate(['reason' => 'required|string|min:5|max:1000', 'date' => 'required|date_format:Y-m-d|before_or_equal:today']));

        return back()->with('success', 'Payment reversed with a balancing ledger entry.');
    }

    public function advances()
    {
        $payments = SalaryAdvanceService::payments()->join('employees as e', 'e.id', '=', 'p.employee_id')->join('companies as c', 'c.id', '=', 'p.company_id')->addSelect('e.full_name', 'c.name as company_name')->orderByDesc('p.id')->paginate(30);
        $recoveries = DB::table('payroll_advance_recoveries as a')->join('payroll_items as i', 'i.id', '=', 'a.payroll_item_id')->join('payroll_runs as r', 'r.id', '=', 'i.payroll_run_id')
            ->whereIn('a.payroll_payment_id', $payments->pluck('id'))->where('r.status', 'approved')
            ->select('a.*', 'r.month', 'r.id as run_id')->orderBy('r.month')->get()->groupBy('payroll_payment_id');
        foreach ($payments as $payment) {
            $payment->recoveries = $recoveries[$payment->id] ?? collect();
        }
        $balances = DB::table('payroll_payments as p')->join('employees as e', 'e.id', '=', 'p.employee_id')->join('companies as c', 'c.id', '=', 'p.company_id')->where('p.kind', 'advance')->select('p.employee_id', 'p.company_id', 'p.currency', 'e.full_name', 'c.name as company_name')->distinct()->get();
        foreach ($balances as $b) {
            $b->balance = PayrollService::advanceBalance($b->employee_id, $b->company_id, $b->currency);
        }

        return view('payroll.advances', $this->options() + ['employees' => Employee::orderBy('full_name')->get(), 'payments' => $payments, 'balances' => $balances]);
    }

    public function storeAdvance(Request $request)
    {
        $data = $request->validate($this->paymentRules() + ['recovery_month' => 'required|date_format:Y-m', 'employee_id' => 'required|exists:employees,id', 'company_id' => 'required|exists:companies,id', 'department_id' => 'required|exists:departments,id', 'currency' => 'required|in:LKR']);
        $id = PayrollService::advance($data);
        $this->receipt($request, $id);

        return back()->with('success', 'Advance recorded with its payroll deduction month.');
    }

    public function scheduleAdvance(Request $request, int $payment)
    {
        $data = $request->validate(['recovery_month' => 'required|date_format:Y-m']);
        SalaryAdvanceService::schedule($payment, $data['recovery_month']);

        return back()->with('success', 'Advance deduction month updated. Review any existing draft payroll before approval.');
    }

    public function downloadReceipt(int $payment)
    {
        $p = DB::table('payroll_payments')->find($payment) ?? abort(404);
        abort_unless($p->receipt_path && Storage::disk(config('filesystems.documents_disk', 'local'))->exists($p->receipt_path), 404);

        return Storage::disk(config('filesystems.documents_disk', 'local'))->download($p->receipt_path, $p->receipt_name);
    }

    public function payslip(int $id, int $item)
    {
        $data = $this->runData($id);
        $record = $data['items']->firstWhere('id', $item) ?? abort(404);
        abort_unless(in_array($data['run']->status, ['approved', 'void']), 422, 'Payslips are available after approval.');

        return Pdf::loadView('payroll.payslip', $data + ['item' => $record, 'salaryPaid' => ($record->paid['salary'] ?? 0)])->download('payslip-'.$id.'-'.$item.'.pdf');
    }

    public function export(int $id)
    {
        $data = $this->runData($id);

        return response()->streamDownload(function () use ($data) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Payroll month', 'Status', 'Company', 'Currency', 'Employee code', 'Employee', 'Basic', 'Gross', 'EPF employee', 'EPF employer', 'ETF', 'APIT', 'Advance recovered', 'Net salary', 'Employer cost', 'Salary paid', 'Salary balance', 'EPF paid', 'ETF paid', 'APIT paid', 'EPF balance', 'ETF balance', 'APIT balance', 'Other payable', 'Other paid', 'Other balance', 'Bank', 'Account', 'EPF number'], ',', '"', '');
            foreach ($data['items'] as $i) {
                $s = $i->snapshot;
                $c = $i->calculation;
                $values = [$data['run']->month, $data['run']->status, $data['company']->name, $data['run']->currency, $s['code'], $s['name'], $c['basic'] / 100, $c['gross'] / 100, $c['ee'] / 100, $c['er'] / 100, $c['etf'] / 100, $c['apit'] / 100, $c['advance'] / 100, $c['net'] / 100, $c['cost'] / 100, ($i->paid['salary'] ?? 0) / 100, ($data['run']->status === 'void' ? 0 : $c['net'] - ($i->paid['salary'] ?? 0)) / 100, ($i->paid['epf'] ?? 0) / 100, ($i->paid['etf'] ?? 0) / 100, ($i->paid['apit'] ?? 0) / 100, ($data['run']->status === 'void' ? 0 : $i->epf_cents - ($i->paid['epf'] ?? 0)) / 100, ($data['run']->status === 'void' ? 0 : $i->etf_cents - ($i->paid['etf'] ?? 0)) / 100, ($data['run']->status === 'void' ? 0 : $i->apit_cents - ($i->paid['apit'] ?? 0)) / 100, $i->other_cents / 100, ($i->paid['other'] ?? 0) / 100, ($data['run']->status === 'void' ? 0 : $i->other_cents - ($i->paid['other'] ?? 0)) / 100, $s['bank'], $s['account'], $s['epf_number']];
                // Prevent spreadsheet formula execution in employee names, references and account fields.
                fputcsv($out, array_map(fn ($v) => is_string($v) && preg_match('/^[\s]*[=+@\-]/', $v) ? "'".$v : $v, $values), ',', '"', '');
            }
            fclose($out);
        }, 'payroll-'.$id.'.csv', ['Content-Type' => 'text/csv']);
    }

    private function runData(int $id): array
    {
        $run = PayrollService::run($id);
        $items = PayrollService::items($id);

        return compact('run', 'items') + ['company' => DB::table('companies')->find($run->company_id), 'balances' => PayrollService::balances($run, $items), 'payments' => DB::table('payroll_payments')->where('payroll_run_id', $id)->orderByDesc('id')->get()];
    }

    private function options(): array
    {
        return ['companies' => DB::table('companies')->orderBy('name')->get(), 'departments' => DB::table('departments')->orderBy('name')->get(), 'banks' => DB::table('bank_accounts')->orderBy('bank_name')->get()];
    }

    private function paymentRules(): array
    {
        return ['amount' => 'required|numeric|min:0.01|max:999999999.99|decimal:0,2', 'paid_on' => 'required|date_format:Y-m-d|before_or_equal:today', 'reference' => 'required|string|max:255', 'request_key' => 'required|uuid', 'bank_account_id' => 'nullable|integer|exists:bank_accounts,id', 'receipt' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120'];
    }

    private function receipt(Request $request, int $id): void
    {
        if ($request->hasFile('receipt')) {
            $file = $request->file('receipt');
            $path = $file->store('payroll-receipts', config('filesystems.documents_disk', 'local'));
            abort_unless($path, 500, 'The receipt could not be stored. The payment was not saved.');
            DB::table('payroll_payments')->where('id', $id)->update(['receipt_path' => $path, 'receipt_name' => basename($file->getClientOriginalName())]);
        }
    }
}
