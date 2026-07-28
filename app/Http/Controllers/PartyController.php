<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PartyController extends Controller
{
    public function index()
    {
        $data = DB::table('parties')->get();
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

        DB::table('parties')->insert($data);
        return back()->with('success', 'Party created successfully!');
    }

    public function update(Request $request, $id)
    {
        $data = $request->except(['_token', '_method']);
        $data['updated_at'] = now();

        if ($request->has('types') && is_array($request->types)) {
            $data['types'] = implode(',', $request->types);
        } else {
            $data['types'] = '';
        }

        DB::table('parties')->where('id', $id)->update($data);
        return back()->with('success', 'Party updated successfully!');
    }

    public function destroy($id)
    {
        $linkedProjects = DB::table('project_party')->where('party_id', $id)->exists();
        $linkedInvoices = DB::table('invoices')->where('client_id', $id)->exists();
        $linkedCommissions = DB::table('project_commissions')->where('party_id', $id)->exists();
        
        if ($linkedProjects || $linkedInvoices || $linkedCommissions) {
            return back()->with('error', 'Cannot delete this party. It is linked to projects, invoices, or commissions. Deactivate it instead.');
        }

        DB::table('parties')->where('id', $id)->delete();
        return back()->with('success', 'Party deleted successfully!');
    }
}
