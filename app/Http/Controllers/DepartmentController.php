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

        DB::table('departments')->insert($data);
        return back()->with('success', 'Department created successfully!');
    }

    public function update(Request $request, $id)
    {
        $data = $request->except(['_token', '_method']);
        $data['updated_at'] = now();

        DB::table('departments')->where('id', $id)->update($data);
        return back()->with('success', 'Department updated successfully!');
    }

    public function destroy($id)
    {
        DB::table('departments')->where('id', $id)->delete();
        return back()->with('success', 'Department deleted successfully!');
    }
}
