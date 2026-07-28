<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use App\Models\Currency;

class CurrencyController extends Controller
{
    private function ensureTablesExist()
    {
        if (!Schema::hasTable('currencies')) {
            Schema::create('currencies', function ($table) {
                $table->id();
                $table->string('code', 3)->unique();
                $table->string('name', 100);
                $table->string('symbol', 10)->default('$');
                $table->boolean('is_active')->default(true);
                $table->boolean('is_base')->default(false);
                $table->timestamps();
            });
            Currency::seedDefaultCurrencies();
        }

        if (DB::table('currencies')->count() === 0) {
            Currency::seedDefaultCurrencies();
        }

        if (!Schema::hasTable('currency_exchange_rates')) {
            Schema::create('currency_exchange_rates', function ($table) {
                $table->id();
                $table->string('base_currency', 3);
                $table->string('target_currency', 3);
                $table->decimal('rate', 18, 6);
                $table->date('rate_date');
                $table->string('source', 50)->default('api');
                $table->timestamps();
            });
        }
    }

    public function index(Request $request)
    {
        $this->ensureTablesExist();

        $company = DB::table('companies')->first();
        $baseCurrencyCode = $company->base_currency ?? 'LKR';

        // Ensure base currency is flagged in currencies table
        DB::table('currencies')->update(['is_base' => 0]);
        DB::table('currencies')->where('code', $baseCurrencyCode)->update(['is_base' => 1]);

        $data = DB::table('currencies')->orderBy('is_base', 'desc')->orderBy('code')->get();

        // Latest rates vs base currency
        $latestRates = DB::table('currency_exchange_rates')
            ->where('base_currency', $baseCurrencyCode)
            ->whereIn('id', function($query) use ($baseCurrencyCode) {
                $query->select(DB::raw('MAX(id)'))
                    ->from('currency_exchange_rates')
                    ->where('base_currency', $baseCurrencyCode)
                    ->groupBy('target_currency');
            })
            ->get()
            ->keyBy('target_currency');

        // Recent History
        $history = DB::table('currency_exchange_rates')
            ->where('base_currency', $baseCurrencyCode)
            ->orderBy('rate_date', 'desc')
            ->orderBy('id', 'desc')
            ->limit(50)
            ->get();

        $tab = 'currencies';

        return view('masters.currencies', compact('data', 'tab', 'baseCurrencyCode', 'latestRates', 'history'));
    }

    public function store(Request $request)
    {
        $this->ensureTablesExist();

        $validated = $request->validate([
            'code' => 'required|string|size:3|unique:currencies,code',
            'name' => 'required|string|max:100',
            'symbol' => 'required|string|max:10',
            'is_active' => 'nullable|boolean',
        ]);

        DB::table('currencies')->insert([
            'code' => strtoupper($validated['code']),
            'name' => $validated['name'],
            'symbol' => $validated['symbol'],
            'is_active' => $request->has('is_active') ? 1 : 0,
            'is_base' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Currency added successfully!');
    }

    public function update(Request $request, $id)
    {
        $this->ensureTablesExist();

        $curr = DB::table('currencies')->where('id', $id)->first();
        if (!$curr) return back()->with('error', 'Currency not found.');

        $isBase = $request->has('is_base') && $request->is_base == 1;

        DB::table('currencies')->where('id', $id)->update([
            'name' => $request->input('name', $curr->name),
            'symbol' => $request->input('symbol', $curr->symbol),
            'is_active' => $request->has('is_active') ? 1 : 0,
            'is_base' => $isBase ? 1 : 0,
            'updated_at' => now(),
        ]);

        if ($isBase) {
            DB::table('currencies')->where('id', '!=', $id)->update(['is_base' => 0]);
            $company = DB::table('companies')->first();
            if ($company) {
                DB::table('companies')->where('id', $company->id)->update([
                    'base_currency' => $curr->code,
                    'updated_at' => now(),
                ]);
            }
        }

        return back()->with('success', 'Currency updated successfully!');
    }

    public function destroy($id)
    {
        $this->ensureTablesExist();

        $curr = DB::table('currencies')->where('id', $id)->first();
        if ($curr && $curr->is_base) {
            return back()->with('error', 'Cannot delete system base currency.');
        }

        DB::table('currencies')->where('id', $id)->delete();
        return back()->with('success', 'Currency deleted successfully!');
    }

    public function syncRates(Request $request)
    {
        $this->ensureTablesExist();

        $company = DB::table('companies')->first();
        $baseCurrency = $company->base_currency ?? 'LKR';

        // Call free Exchange Rate API
        $apiUrl = "https://open.er-api.com/v6/latest/{$baseCurrency}";
        $response = Http::timeout(10)->get($apiUrl);

        if (!$response->successful()) {
            // Fallback API if first API fails
            $apiUrl = "https://api.exchangerate-api.com/v4/latest/{$baseCurrency}";
            $response = Http::timeout(10)->get($apiUrl);
        }

        if (!$response->successful()) {
            return back()->with('error', 'Failed to fetch live exchange rates from conversion API.');
        }

        $responseData = $response->json();
        $rates = $responseData['rates'] ?? [];

        if (empty($rates)) {
            return back()->with('error', 'Exchange rate API returned empty rates payload.');
        }

        $activeCurrencies = DB::table('currencies')->where('is_active', 1)->get();
        $today = date('Y-m-d');
        $syncedCount = 0;

        foreach ($activeCurrencies as $currency) {
            $code = $currency->code;
            if (isset($rates[$code])) {
                $rate = (float) $rates[$code];
                
                DB::table('currency_exchange_rates')->insert([
                    'base_currency' => $baseCurrency,
                    'target_currency' => $code,
                    'rate' => $rate,
                    'rate_date' => $today,
                    'source' => 'api',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $syncedCount++;
            }
        }

        return back()->with('success', "Successfully synced daily exchange rates for {$syncedCount} currencies relative to Base ({$baseCurrency})!");
    }

    public function history(Request $request, $code)
    {
        $this->ensureTablesExist();

        $company = DB::table('companies')->first();
        $baseCurrency = $company->base_currency ?? 'LKR';

        $history = DB::table('currency_exchange_rates')
            ->where('base_currency', $baseCurrency)
            ->where('target_currency', $code)
            ->orderBy('rate_date', 'desc')
            ->orderBy('id', 'desc')
            ->limit(100)
            ->get();

        return response()->json([
            'status' => 'success',
            'base' => $baseCurrency,
            'target' => $code,
            'data' => $history,
        ]);
    }
}
