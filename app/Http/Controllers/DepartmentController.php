<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DepartmentController extends Controller
{
    public function index()
    {
        $data = DB::table('departments')->get();
        $companies = DB::table('companies')->get();
        return view('masters.departments', compact('data', 'companies'));
    }

    public function store(Request $request)
    {
        $data = $request->except(['_token']);
        $data['created_at'] = now();
        $data['updated_at'] = now();

        if (!isset($data['company_id'])) {
            $company = DB::table('companies')->first();
            $data['company_id'] = $company ? $company->id : 1;
        }

        if (empty($data['parent_id'])) {
            $data['parent_id'] = null;
        }

        $id = DB::table('departments')->insertGetId($data);

        \App\Services\ActivityLogService::logCreate('Department', $id, $data);

        return back()->with('success', 'Department created successfully!');
    }

    public function update(Request $request, $id)
    {
        $oldData = DB::table('departments')->where('id', $id)->first();
        $data = $request->except(['_token', '_method']);
        $data['updated_at'] = now();

        if (isset($data['parent_id']) && (int)$data['parent_id'] === (int)$id) {
            $data['parent_id'] = null;
        }
        if (empty($data['parent_id'])) {
            $data['parent_id'] = null;
        }

        DB::table('departments')->where('id', $id)->update($data);

        \App\Services\ActivityLogService::logUpdate('Department', $id, $oldData, $data);

        return back()->with('success', 'Department updated successfully!');
    }


    public function destroy($id)
    {
        $oldData = DB::table('departments')->where('id', $id)->first();
        DB::table('departments')->where('id', $id)->delete();

        \App\Services\ActivityLogService::logDelete('Department', $id, $oldData);

        return back()->with('success', 'Department deleted successfully!');
    }
}

