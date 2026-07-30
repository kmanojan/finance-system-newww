<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CategoryController extends Controller
{
    public function index()
    {
        $data = DB::table('categories')->get();
        return view('masters.categories', compact('data'));
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

        $id = DB::table('categories')->insertGetId($data);

        \App\Services\ActivityLogService::logCreate('Category', $id, $data);

        return back()->with('success', 'Category created successfully!');
    }

    public function update(Request $request, $id)
    {
        $oldData = DB::table('categories')->where('id', $id)->first();
        $data = $request->except(['_token', '_method']);
        $data['updated_at'] = now();

        DB::table('categories')->where('id', $id)->update($data);

        \App\Services\ActivityLogService::logUpdate('Category', $id, $oldData, $data);

        return back()->with('success', 'Category updated successfully!');
    }

    public function destroy($id)
    {
        $oldData = DB::table('categories')->where('id', $id)->first();
        DB::table('categories')->where('id', $id)->delete();

        \App\Services\ActivityLogService::logDelete('Category', $id, $oldData);

        return back()->with('success', 'Category deleted successfully!');
    }
}

