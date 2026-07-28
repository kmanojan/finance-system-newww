<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BankAccountController extends Controller
{
    public function index()
    {
        $data = DB::table('bank_accounts')->get();
        foreach ($data as $item) {
            $inflow = DB::table('transactions')->where('bank_account_id', $item->id)->where('type', 'income')->sum('amount');
            $outflow = DB::table('transactions')->where('bank_account_id', $item->id)->where('type', 'expense')->sum('amount');
            $item->current_balance = ($item->opening_balance ?? 0) + $inflow - $outflow;
        }
        return view('masters.bank_accounts', compact('data'));
    }

    public function store(Request $request)
    {
        $data = $request->except(['_token']);
        $data['created_at'] = now();
        $data['updated_at'] = now();

        unset($data['name']);
        if (isset($data['current_balance'])) {
            $data['opening_balance'] = $data['current_balance'];
            unset($data['current_balance']);
        }

        if (!isset($data['company_id'])) {
            $company = DB::table('companies')->first();
            $data['company_id'] = $company ? $company->id : 1;
        }

        DB::table('bank_accounts')->insert($data);
        return back()->with('success', 'Bank account created successfully!');
    }

    public function update(Request $request, $id)
    {
        $data = $request->except(['_token', '_method']);
        $data['updated_at'] = now();

        unset($data['name']);
        if (isset($data['current_balance'])) {
            $data['opening_balance'] = $data['current_balance'];
            unset($data['current_balance']);
        }

        DB::table('bank_accounts')->where('id', $id)->update($data);
        return back()->with('success', 'Bank account updated successfully!');
    }

    public function destroy($id)
    {
        DB::table('bank_accounts')->where('id', $id)->delete();
        return back()->with('success', 'Bank account deleted successfully!');
    }
}
