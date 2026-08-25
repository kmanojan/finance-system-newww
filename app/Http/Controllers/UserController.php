<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Department;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Display a listing of the users.
     */
    public function index(Request $request)
    {
        $query = User::with('department')->latest();

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        if ($request->filled('status')) {
            if ($request->status === 'active') {
                $query->where('is_active', true);
            } elseif ($request->status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        $users = $query->get();
        $departments = Department::orderBy('name')->get();

        $roles = [
            'admin' => 'Admin',
            'manager' => 'Manager',
            'accountant' => 'Accountant',
            'staff' => 'Staff',
            'viewer' => 'Viewer',
        ];

        return view('masters.users', compact('users', 'departments', 'roles'));
    }

    /**
     * Store a newly created user in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|string|in:admin,manager,accountant,staff,viewer',
            'department_id' => 'nullable|exists:departments,id',
            'phone' => 'nullable|string|max:20',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'department_id' => $validated['department_id'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : true,
        ]);

        ActivityLogService::logCreate('User', $user->id, [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'is_active' => $user->is_active,
        ]);

        return back()->with('success', 'User created successfully!');
    }

    /**
     * Update the specified user in storage.
     */
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => 'nullable|string|min:8|confirmed',
            'role' => 'required|string|in:admin,manager,accountant,staff,viewer',
            'department_id' => 'nullable|exists:departments,id',
            'phone' => 'nullable|string|max:20',
        ]);

        $newIsActive = $request->has('is_active') ? $request->boolean('is_active') : false;

        // Prevent self-deactivation
        if (Auth::id() === $user->id && !$newIsActive) {
            return back()->with('error', 'You cannot deactivate your own account.');
        }

        // Prevent deactivating or demoting the last active admin
        if ($user->role === 'admin' && ($validated['role'] !== 'admin' || !$newIsActive)) {
            $activeAdminCount = User::where('role', 'admin')->where('is_active', true)->where('id', '!=', $user->id)->count();
            if ($activeAdminCount < 1) {
                return back()->with('error', 'Cannot deactivate or change the role of the only active administrator.');
            }
        }

        $oldData = [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'department_id' => $user->department_id,
            'phone' => $user->phone,
            'is_active' => $user->is_active,
        ];

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->role = $validated['role'];
        $user->department_id = $validated['department_id'] ?? null;
        $user->phone = $validated['phone'] ?? null;
        $user->is_active = $newIsActive;

        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        ActivityLogService::logUpdate('User', $user->id, $oldData, [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'department_id' => $user->department_id,
            'phone' => $user->phone,
            'is_active' => $user->is_active,
        ]);

        return back()->with('success', 'User updated successfully!');
    }

    /**
     * Remove the specified user from storage (Soft Delete).
     */
    public function destroy($id)
    {
        $user = User::findOrFail($id);

        // Prevent self-deletion
        if (Auth::id() === $user->id) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        // Prevent deleting the last active admin
        if ($user->role === 'admin') {
            $activeAdminCount = User::where('role', 'admin')->where('is_active', true)->where('id', '!=', $user->id)->count();
            if ($activeAdminCount < 1) {
                return back()->with('error', 'Cannot delete the only active administrator.');
            }
        }

        $oldData = [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
        ];

        $user->delete();

        ActivityLogService::logDelete('User', $user->id, $oldData);

        return back()->with('success', 'User deleted successfully!');
    }

    /**
     * Toggle the active status of the specified user.
     */
    public function toggleStatus($id)
    {
        $user = User::findOrFail($id);

        // Prevent self-deactivation
        if (Auth::id() === $user->id) {
            return back()->with('error', 'You cannot change the status of your own account.');
        }

        // Prevent deactivating the last active admin
        if ($user->is_active && $user->role === 'admin') {
            $activeAdminCount = User::where('role', 'admin')->where('is_active', true)->where('id', '!=', $user->id)->count();
            if ($activeAdminCount < 1) {
                return back()->with('error', 'Cannot deactivate the only active administrator.');
            }
        }

        $oldStatus = $user->is_active;
        $user->is_active = !$user->is_active;
        $user->save();

        ActivityLogService::logUpdate('User', $user->id, ['is_active' => $oldStatus], ['is_active' => $user->is_active]);

        $statusText = $user->is_active ? 'activated' : 'deactivated';
        return back()->with('success', "User '{$user->name}' has been {$statusText} successfully!");
    }
}
