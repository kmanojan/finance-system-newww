<?php

namespace App\Http\Controllers;

use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function index(Request $request)
    {
        $tab = $request->query('tab', 'general');
        $user = Auth::user();
        if ($user) {
            $user->load('department');
        }
        $company = DB::table('companies')->first();
        
        return view('profile', compact('tab', 'user', 'company'));
    }

    public function updateGeneral(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'phone' => 'nullable|string|max:20',
        ]);

        $oldData = [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
        ];

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->phone = $validated['phone'] ?? null;
        $user->save();

        ActivityLogService::logUpdate('User Profile', $user->id, $oldData, [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
        ]);

        return redirect('/profile?tab=general')->with('success', 'Profile details updated successfully.');
    }

    public function updatePassword(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user->password = Hash::make($validated['password']);
        $user->save();

        ActivityLogService::log('Password Changed', 'User Profile', $user->id);

        return redirect('/profile?tab=general')->with('success', 'Password has been changed successfully.');
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
