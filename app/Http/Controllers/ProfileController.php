<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProfileController extends Controller
{
    public function index(Request $request)
    {
        $tab = $request->query('tab', 'config');
        $company = DB::table('companies')->first();
        
        return view('profile', compact('tab', 'company'));
    }

    public function updateConfig(Request $request)
    {
        $request->validate([
            'base_currency' => 'required|string|max:10',
        ]);

        $company = DB::table('companies')->first();
        if ($company) {
            DB::table('companies')->where('id', $company->id)->update([
                'base_currency' => $request->base_currency,
                'updated_at' => now(),
            ]);
        } else {
            DB::table('companies')->insert([
                'name' => 'Default Company',
                'base_currency' => $request->base_currency,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return redirect('/profile?tab=config')->with('success', 'Configuration updated successfully.');
    }
}
