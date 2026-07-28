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

        DB::table('categories')->insert($data);
        return back()->with('success', 'Category created successfully!');
    }

    public function update(Request $request, $id)
    {
        $data = $request->except(['_token', '_method']);
        $data['updated_at'] = now();

        DB::table('categories')->where('id', $id)->update($data);
        return back()->with('success', 'Category updated successfully!');
    }

    public function destroy($id)
    {
        DB::table('categories')->where('id', $id)->delete();
        return back()->with('success', 'Category deleted successfully!');
    }
}
