<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvoiceTypeController extends Controller
{
    public function index()
    {
        $data = DB::table('invoice_types')->get();
        $categories = DB::table('categories')->get();
        return view('masters.invoice_types', compact('data', 'categories'));
    }

    public function store(Request $request)
    {
        $data = $request->except(['_token']);
        $data['created_at'] = now();
        $data['updated_at'] = now();

        DB::table('invoice_types')->insert($data);
        return back()->with('success', 'Invoice type created successfully!');
    }

    public function update(Request $request, $id)
    {
        $data = $request->except(['_token', '_method']);
        $data['updated_at'] = now();

        DB::table('invoice_types')->where('id', $id)->update($data);
        return back()->with('success', 'Invoice type updated successfully!');
    }

    public function destroy($id)
    {
        DB::table('invoice_types')->where('id', $id)->delete();
        return back()->with('success', 'Invoice type deleted successfully!');
    }
}
