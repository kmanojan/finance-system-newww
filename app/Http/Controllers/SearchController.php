<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SearchController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['q' => 'nullable|string|max:100']);
        $q = trim($request->input('q', ''));
        $results = [];
        if (mb_strlen($q) >= 2) {
            foreach (DB::table('projects')->where('name', 'like', '%'.$q.'%')->limit(15)->get() as $row) {
                $results[] = ['type' => 'Project', 'name' => $row->name, 'detail' => $row->status, 'url' => '/projects/'.$row->id];
            }
            foreach (DB::table('invoices')->where('invoice_no', 'like', '%'.$q.'%')->limit(15)->get() as $row) {
                $results[] = ['type' => 'Invoice', 'name' => $row->invoice_no, 'detail' => $row->currency.' '.number_format($row->amount, 2), 'url' => '/invoices?search='.urlencode($row->invoice_no)];
            }
            foreach (DB::table('parties')->whereNull('deleted_at')->where('name', 'like', '%'.$q.'%')->limit(15)->get() as $row) {
                $results[] = ['type' => 'Party', 'name' => $row->name, 'detail' => $row->email, 'url' => '/master/parties?search='.urlencode($row->name)];
            }
            foreach (DB::table('transactions')->where(fn ($query) => $query->where('description', 'like', '%'.$q.'%')->orWhere('reference_no', 'like', '%'.$q.'%'))->limit(15)->get() as $row) {
                $results[] = ['type' => 'Transaction', 'name' => $row->description ?: $row->reference_no, 'detail' => $row->currency.' '.number_format($row->amount, 2), 'url' => '/transactions?search='.urlencode($q)];
            }
        }

        return view('search', compact('q', 'results'));
    }
}
