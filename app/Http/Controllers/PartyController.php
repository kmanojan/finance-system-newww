<?php

namespace App\Http\Controllers;

use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PartyController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('parties')->orderBy('name', 'asc')
            ->select('parties.*')
            ->selectSub(DB::table('employees')->select('id')->whereColumn('employees.party_id', 'parties.id'), 'employee_id')
            ->selectSub(DB::table('employees')->select('employee_code')->whereColumn('employees.party_id', 'parties.id'), 'employee_code');

        if ($request->filled('party_id')) {
            $query->where('parties.id', $request->integer('party_id'));
        }

        if ($request->filled('search')) {
            $search = '%'.$request->input('search').'%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', $search)
                    ->orWhere('contact_person', 'LIKE', $search)
                    ->orWhere('email', 'LIKE', $search)
                    ->orWhere('phone', 'LIKE', $search)
                    ->orWhere('types', 'LIKE', $search);
            });
        }

        if ($request->filled('type') && $request->input('type') !== 'all') {
            $query->where('types', 'LIKE', '%'.$request->input('type').'%');
        }

        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->input('status'));
        }

        $data = $query->paginate(15)->withQueryString();

        foreach ($data as $item) {
            $item->projects_count = DB::table('project_party')
                ->where('party_id', $item->id)
                ->distinct('project_id')
                ->count();
        }

        return view('masters.parties', compact('data'));
    }

    public function store(Request $request)
    {
        $data = $request->except(['_token']);
        $data['created_at'] = now();
        $data['updated_at'] = now();

        if ($request->has('types') && is_array($request->types)) {
            $data['types'] = implode(',', $request->types);
        } else {
            $data['types'] = '';
        }

        $id = DB::table('parties')->insertGetId($data);

        ActivityLogService::logCreate('Party', $id, $data);

        return back()->with('success', 'Party created successfully!');
    }

    public function update(Request $request, $id)
    {
        $oldData = DB::table('parties')->where('id', $id)->first();
        $data = $request->except(['_token', '_method']);
        $data['updated_at'] = now();

        if ($request->has('types') && is_array($request->types)) {
            $data['types'] = implode(',', $request->types);
        } else {
            $data['types'] = '';
        }

        $employee = DB::table('employees')->where('party_id', $id)->first();
        if ($employee) {
            $data['types'] = implode(',', array_unique([...array_filter(explode(',', $data['types'])), 'employee']));
            // The employee profile owns identity; party roles, notes and status remain editable here.
            $data['name'] = $employee->full_name;
            $data['email'] = $employee->personal_email;
            $data['phone'] = $employee->mobile_phone;
        }
        DB::table('parties')->where('id', $id)->update($data);

        ActivityLogService::logUpdate('Party', $id, $oldData, $data);

        return back()->with('success', 'Party updated successfully!');
    }

    public function destroy($id)
    {
        if (DB::table('employees')->where('party_id', $id)->exists()) {
            return back()->with('error', 'Cannot delete an employee-linked party. Keep it for salary and loan history; deactivate it if needed.');
        }
        $linkedProjects = DB::table('project_party')->where('party_id', $id)->exists();
        $linkedInvoices = DB::table('invoices')->where('client_id', $id)->exists();
        $linkedCommissions = DB::table('project_commissions')->where('party_id', $id)->exists();

        if ($linkedProjects || $linkedInvoices || $linkedCommissions) {
            return back()->with('error', 'Cannot delete this party. It is linked to projects, invoices, or commissions. Deactivate it instead.');
        }

        $oldData = DB::table('parties')->where('id', $id)->first();
        DB::table('parties')->where('id', $id)->delete();

        ActivityLogService::logDelete('Party', $id, $oldData);

        return back()->with('success', 'Party deleted successfully!');
    }
}
