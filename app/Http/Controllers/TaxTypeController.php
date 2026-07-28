<?php

namespace App\Http\Controllers;

use App\Models\TaxType;
use App\Services\TaxCalculator;
use Illuminate\Http\Request;

class TaxTypeController extends Controller
{
    public function index(Request $request)
    {
        $query = TaxType::query();

        if ($request->has('category') && !empty($request->category)) {
            $query->where('category', $request->category);
        }

        if ($request->has('applies_to') && !empty($request->applies_to)) {
            $query->where('applies_to', $request->applies_to);
        }

        if ($request->boolean('active_only')) {
            $query->active();
        }

        $taxTypes = $query->orderBy('category')->orderBy('name')->get();

        if ($request->wantsJson() || $request->is('api/*')) {
            return response()->json([
                'success' => true,
                'data' => $taxTypes
            ]);
        }

        return view('masters.tax_types', compact('taxTypes'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'required|in:vat,wht,cit,other',
            'rate' => 'required|numeric|min:0|max:100',
            'effective_from' => 'required|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
            'applies_to' => 'required|string|max:100',
            'is_default' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        if (!empty($validated['is_default'])) {
            TaxType::where('category', $validated['category'])
                ->where('applies_to', $validated['applies_to'])
                ->update(['is_default' => false]);
        }

        $taxType = TaxType::create([
            'name' => $validated['name'],
            'category' => $validated['category'],
            'rate' => $validated['rate'],
            'effective_from' => $validated['effective_from'],
            'effective_to' => $validated['effective_to'] ?? null,
            'applies_to' => $validated['applies_to'],
            'is_default' => $request->boolean('is_default'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        if ($request->wantsJson() || $request->is('api/*')) {
            return response()->json([
                'success' => true,
                'message' => 'Tax type created successfully.',
                'data' => $taxType
            ], 201);
        }

        return redirect()->back()->with('success', 'Tax type created successfully.');
    }

    public function update(Request $request, $id)
    {
        $taxType = TaxType::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'category' => 'sometimes|required|in:vat,wht,cit,other',
            'rate' => 'sometimes|required|numeric|min:0|max:100',
            'effective_from' => 'sometimes|required|date',
            'effective_to' => 'nullable|date',
            'applies_to' => 'sometimes|required|string|max:100',
            'is_default' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        if (!empty($validated['is_default']) && $validated['is_default']) {
            TaxType::where('category', $taxType->category)
                ->where('applies_to', $taxType->applies_to)
                ->where('id', '!=', $id)
                ->update(['is_default' => false]);
        }

        $taxType->update($request->all());

        if ($request->wantsJson() || $request->is('api/*')) {
            return response()->json([
                'success' => true,
                'message' => 'Tax type updated successfully.',
                'data' => $taxType
            ]);
        }

        return redirect()->back()->with('success', 'Tax type updated successfully.');
    }

    public function destroy(Request $request, $id)
    {
        $taxType = TaxType::findOrFail($id);
        $taxType->delete();

        if ($request->wantsJson() || $request->is('api/*')) {
            return response()->json([
                'success' => true,
                'message' => 'Tax type soft-deleted successfully.'
            ]);
        }

        return redirect()->back()->with('success', 'Tax type deleted successfully.');
    }

    public function calculate(Request $request)
    {
        $amount = (float) $request->input('amount', 0);
        $type = $request->input('type', 'vat');
        $taxTypeId = $request->input('tax_type_id');
        $appliesTo = $request->input('applies_to', 'commission_payment');

        if ($type === 'vat') {
            $result = TaxCalculator::vat($amount, $taxTypeId);
        } elseif ($type === 'apit') {
            $result = TaxCalculator::apit($amount);
        } else {
            $result = TaxCalculator::wht($amount, $taxTypeId, $appliesTo);
        }


        return response()->json([
            'success' => true,
            'data' => $result
        ]);
    }
}
