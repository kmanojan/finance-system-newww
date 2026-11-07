<?php

namespace App\Http\Controllers;

use App\Services\PayrollService;
use App\Services\PayrollStatutoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class PayrollStatutoryController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate(['company_id' => 'nullable|integer|exists:companies,id', 'month' => 'nullable|date_format:Y-m',
            'kind' => ['nullable', Rule::in(array_keys(PayrollStatutoryService::KINDS))],
            'status' => ['nullable', Rule::in(['open', 'all', 'unpaid', 'prepared_unpaid', 'paid_unsubmitted', 'overdue'])]]);
        $query = PayrollStatutoryService::register($filters);
        $summary = (clone $query)->selectRaw("COUNT(*) as obligations, COALESCE(SUM(balance_cents),0) as balance_cents, COALESCE(SUM(CASE WHEN event_id IS NOT NULL THEN balance_cents ELSE 0 END),0) as prepared_balance_cents, COALESCE(SUM(CASE WHEN balance_cents <= 0 AND (action IS NULL OR action != 'submitted') THEN 1 ELSE 0 END),0) as paid_unsubmitted_count")->first();
        $rows = $query->orderBy('due_on')->orderBy('month')->orderBy('run_id')->orderBy('kind')->paginate(30)->withQueryString();
        foreach ($rows as $row) {
            $row->tracking = PayrollStatutoryService::state($row->run_status, (int) $row->due_cents, (int) $row->paid_cents, $row->due_on,
                $row->event_id ? (object) ['id' => $row->event_id, 'action' => $row->action] : null);
        }

        return view('payroll.statutory-index', ['rows' => $rows, 'summary' => $summary, 'filters' => $filters,
            'companies' => DB::table('companies')->orderBy('name')->get()]);
    }

    public function show(int $id, string $kind)
    {
        return view('payroll.statutory-detail', $this->data($id, $kind));
    }

    public function record(Request $request, int $id, string $kind, string $action)
    {
        if ($action === 'reopened') {
            abort_unless(in_array(strtolower($request->user()->role), ['admin', 'manager']), 403);
        }
        $request->validate(['evidence' => 'nullable|file|mimes:pdf,jpg,jpeg,png,xlsx,csv|max:5120']);
        $eventId = PayrollStatutoryService::record($id, $kind, $action, $request->all());
        if ($request->hasFile('evidence')) {
            $file = $request->file('evidence');
            $path = $file->store('payroll-statutory', config('filesystems.documents_disk', 'local'));
            abort_unless($path, 500, 'The supporting document could not be stored. The statutory update was not saved.');
            DB::table('payroll_statutory_events')->where('id', $eventId)->update(['evidence_path' => $path, 'evidence_name' => basename($file->getClientOriginalName())]);
        }

        return redirect('/payroll/'.$id.'/statutory/'.$kind)->with('success', 'Statutory '.($action === 'reopened' ? 'submission reopened' : ($action === 'prepared' ? 'preparation recorded' : 'submission recorded')).'. Payment balances are unchanged.');
    }

    public function payment(Request $request, int $id, string $kind)
    {
        abort_unless(isset(PayrollStatutoryService::KINDS[$kind]), 404);
        $data = $request->validate(['amount' => 'required|numeric|min:0.01|max:999999999.99|decimal:0,2',
            'paid_on' => 'required|date_format:Y-m-d|before_or_equal:'.PayrollStatutoryService::today(), 'reference' => 'required|string|max:255',
            'request_key' => 'required|uuid', 'bank_account_id' => 'nullable|integer|exists:bank_accounts,id',
            'receipt' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120']);
        $payment = PayrollService::payment($id, $data + ['kind' => $kind]);
        if ($request->hasFile('receipt')) {
            $file = $request->file('receipt');
            $path = $file->store('payroll-receipts', config('filesystems.documents_disk', 'local'));
            abort_unless($path, 500, 'The payment receipt could not be stored. The payment was not saved.');
            DB::table('payroll_payments')->where('id', $payment)->update(['receipt_path' => $path, 'receipt_name' => basename($file->getClientOriginalName())]);
        }

        return redirect('/payroll/'.$id.'/statutory/'.$kind.'#remittance-payments')->with('success', 'Actual statutory payment recorded. Preparation and submission tracking are unchanged.');
    }

    public function evidence(int $event)
    {
        $record = DB::table('payroll_statutory_events')->find($event) ?? abort(404);
        $disk = Storage::disk(config('filesystems.documents_disk', 'local'));
        abort_unless($record->evidence_path && $disk->exists($record->evidence_path), 404);

        return $disk->download($record->evidence_path, $record->evidence_name);
    }

    public function schedule(int $id, string $kind)
    {
        $data = $this->data($id, $kind);

        return response()->streamDownload(function () use ($data, $kind) {
            $out = fopen('php://output', 'w');
            $specific = match ($kind) {
                'epf' => ['EPF base', 'Employee EPF', 'Employer EPF'], 'etf' => ['ETF base'], 'apit' => ['Taxable earnings estimate'],
            };
            fputcsv($out, ['Internal review schedule; not an official filing format'], ',', '"', '');
            fputcsv($out, ['Payroll month', 'Company', 'Currency', 'Employee code', 'Employee', 'EPF member number', ...$specific, 'Amount due', 'Recorded paid', 'Remaining'], ',', '"', '');
            foreach ($data['items'] as $item) {
                $due = $item->{PayrollService::column($kind)};
                if ($due === 0) {
                    continue;
                }
                $c = $item->calculation;
                $specific = match ($kind) {
                    'epf' => [$c['epfBase'] / 100, $c['ee'] / 100, $c['er'] / 100], 'etf' => [$c['etfBase'] / 100], 'apit' => [$c['taxable'] / 100],
                };
                $paid = (int) ($item->paid[$kind] ?? 0);
                $row = [$data['run']->month, $data['company']->name, $data['run']->currency, $item->snapshot['code'], $item->snapshot['name'], $item->snapshot['epf_number'], ...$specific, $due / 100, $paid / 100, ($data['run']->status === 'void' ? 0 : $due - $paid) / 100];
                fputcsv($out, array_map(fn ($value) => is_string($value) && preg_match('/^[\s]*[=+@\-]/', $value) ? "'".$value : $value, $row), ',', '"', '');
            }
            fclose($out);
        }, 'payroll-'.$id.'-'.$kind.'-review.csv', ['Content-Type' => 'text/csv']);
    }

    private function data(int $id, string $kind): array
    {
        abort_unless(isset(PayrollStatutoryService::KINDS[$kind]), 404);
        $run = PayrollService::run($id);
        $items = PayrollService::items($id);

        return compact('run', 'items', 'kind') + ['state' => PayrollStatutoryService::states($run, $items)[$kind],
            'company' => DB::table('companies')->find($run->company_id), 'label' => PayrollStatutoryService::KINDS[$kind],
            'today' => PayrollStatutoryService::today(), 'banks' => DB::table('bank_accounts')->where('company_id', $run->company_id)->where('currency', $run->currency)->get(),
            'events' => DB::table('payroll_statutory_events as e')->leftJoin('users as u', 'u.id', '=', 'e.created_by')->where('e.payroll_run_id', $id)->where('e.kind', $kind)->select('e.*', 'u.name as recorded_by')->orderByDesc('e.id')->get(),
            'payments' => DB::table('payroll_payments')->where('payroll_run_id', $id)->where('kind', $kind)->orderByDesc('id')->get()];
    }
}
