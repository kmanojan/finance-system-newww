<?php

namespace App\Http\Controllers;

use App\Models\SalaryComponent;
use App\Models\EmployeeSalaryComponent;
use Illuminate\Http\Request;

class SalaryComponentController extends Controller
{
    public function index(Request $request)
    {
        $query = SalaryComponent::query();

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('calculation_type')) {
            $query->where('calculation_type', $request->calculation_type);
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('code', 'like', "%{$request->search}%")
                  ->orWhere('description', 'like', "%{$request->search}%");
            });
        }

        $components = $query->orderBy('sort_order')->orderBy('id')->get();

        if ($request->wantsJson() || $request->is('api/*')) {
            return response()->json($components);
        }

        return view('masters.salary_components', compact('components'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'             => 'required|string|max:255',
            'code'             => 'required|string|max:50|unique:salary_components,code',
            'type'             => 'required|in:earning,deduction,employer_contribution',
            'calculation_type' => 'required|in:fixed_amount,percentage',
            'calculation_base' => 'required|in:on_basic,on_epf_base,on_gross,custom',
            'default_value'    => 'required|numeric|min:0',
            'is_epf_eligible'  => 'boolean',
            'is_taxable'       => 'boolean',
            'is_statutory'     => 'boolean',
            'is_active'        => 'boolean',
            'description'      => 'nullable|string',
            'sort_order'       => 'nullable|integer',
        ]);

        $validated['is_epf_eligible'] = $request->boolean('is_epf_eligible');
        $validated['is_taxable']      = $request->boolean('is_taxable');
        $validated['is_statutory']    = $request->boolean('is_statutory');
        $validated['is_active']       = $request->boolean('is_active', true);
        $validated['sort_order']      = $request->input('sort_order', 10);

        $component = SalaryComponent::create($validated);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'component' => $component]);
        }

        return redirect()->back()->with('success', "Salary component '{$component->name}' created successfully.");
    }

    public function update(Request $request, $id)
    {
        $component = SalaryComponent::findOrFail($id);

        $validated = $request->validate([
            'name'             => 'required|string|max:255',
            'code'             => 'required|string|max:50|unique:salary_components,code,' . $component->id,
            'type'             => 'required|in:earning,deduction,employer_contribution',
            'calculation_type' => 'required|in:fixed_amount,percentage',
            'calculation_base' => 'required|in:on_basic,on_epf_base,on_gross,custom',
            'default_value'    => 'required|numeric|min:0',
            'is_epf_eligible'  => 'boolean',
            'is_taxable'       => 'boolean',
            'is_statutory'     => 'boolean',
            'is_active'        => 'boolean',
            'description'      => 'nullable|string',
            'sort_order'       => 'nullable|integer',
        ]);

        $validated['is_epf_eligible'] = $request->boolean('is_epf_eligible');
        $validated['is_taxable']      = $request->boolean('is_taxable');
        $validated['is_statutory']    = $request->boolean('is_statutory');
        $validated['is_active']       = $request->boolean('is_active', true);

        $component->update($validated);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'component' => $component]);
        }

        return redirect()->back()->with('success', "Salary component '{$component->name}' updated successfully.");
    }

    public function destroy($id)
    {
        $component = SalaryComponent::findOrFail($id);

        // Guard against deleting core statutory components
        if ($component->is_statutory && in_array($component->code, ['BASIC', 'EPF_EE', 'EPF_ER', 'ETF_ER'])) {
            return redirect()->back()->with('error', "Core statutory component '{$component->name}' cannot be deleted. You can deactivate it instead.");
        }

        $component->delete();

        return redirect()->back()->with('success', "Salary component '{$component->name}' removed successfully.");
    }
}
