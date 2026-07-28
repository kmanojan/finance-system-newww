# Tax Module — Full Specification (Config, Rates, Service, Controller, Seeder)

*Written the way I'd brief you if I were running Finance for a software company at real scale — export-heavy revenue, sub-companies, contractors, and enough cash flow that every one of these taxes actually bites if it's missed.*

Rates below are current as of mid-2026 (Sri Lanka). Tax law here changes almost every fiscal year — treat this as the baseline your system should be **configurable** around, not a hardcoded truth. Always re-verify with your auditor before a filing.

---

## 1. The full picture — what actually applies to you

| Tax | Rate | Who it hits | Frequency |
|---|---|---|---|
| **VAT** | 18% standard | Local invoices (services consumed in Sri Lanka); exports (services consumed outside SL) are typically **zero-rated** | Monthly/quarterly return |
| **VAT — Digital Services** | 18% | Non-resident providers selling digital services to SL consumers (from July 1, 2026) — only relevant if you sell a SaaS product to SL consumers directly | Monthly |
| **Corporate Income Tax (CIT)** | 30% standard; **15% concessionary** for IT/BPO service exports (since April 1, 2025 — previously exempt) | Company profit, annually | Annual, with quarterly Advance Income Tax installments |
| **WHT — Service fees** | 5% | Payments to **resident individuals** (freelancers, consultants) where the aggregate exceeds LKR 100,000/month | At time of payment |
| **WHT — Interest & Rent** | 10% | Interest paid (e.g., on your third-party loans), rent paid | At time of payment |
| **WHT — Royalties, non-resident specified payments** | 14% | Royalty payments, payments to non-residents for services | At time of payment |
| **WHT — Dividends** | 15% | Dividend distributions to shareholders | At time of payment |
| **WHT — Specified non-resident payments** | 2% | Certain non-resident payment categories | At time of payment |
| **APIT** (Advance Personal Income Tax — replaced PAYE) | Progressive, 6 bands, 6%–36%, personal relief LKR 1,800,000/year | Employee salaries | Monthly, via payroll |
| **EPF** | 8% employee + 12% employer | Employee salaries (statutory, not strictly a tax) | Monthly |
| **ETF** | 3% employer | Employee salaries (statutory) | Monthly |
| **Stamp Duty** | Varies by instrument | Agreements, share transfers | Per transaction |
| **Capital Gains Tax** | 10% | Gains on disposal of investment assets (shares, property) | Per disposal |

**The one that matters most to you as an exporter:** foreign-currency service export income used to be fully tax-exempt. Since April 1, 2025, it's taxed at a flat **15%**, provided the earnings are remitted through a Sri Lankan bank — if you keep earnings offshore, you're back to standard progressive/30% rates. Practically: always remit through your local bank account (§1.6 Bank Accounts) to keep the concessionary rate.

---

## 2. How this maps into the system

- **VAT** → per invoice line item (§3.5), rate resolved by `TaxCalculator::vat()`. Export invoices (client's billing country ≠ Sri Lanka) should default to a 0% "VAT — Zero-Rated Export" tax type rather than the 18% standard one — the single most important config to get right for an export-heavy company.
- **WHT** → applied at payout time, not invoice time — Commission Payments (§4.5) and Loan Interest Payments (§10) both need a `tax_type_id` selectable per payment, using `TaxCalculator::wht()`.
- **CIT** → doesn't touch individual transactions. Computed annually off P&L net profit (§12) — 15% if export/foreign-remitted revenue, 30% otherwise. Your P&L report should split net profit by revenue source (export vs domestic) so both rates apply correctly.
- **APIT/EPF/ETF** → payroll-only, out of scope unless Payroll-lite (§13) is built.
- **Stamp Duty / Capital Gains** → occasional, logged manually via Project Documents or a one-off transaction, not a rate engine.

---

## 3. Compliance calendar (ties into Reminders, §8)

| Obligation | Due |
|---|---|
| VAT return & payment | Monthly, by the 20th of the following month |
| WHT remittance | Monthly, by the 15th of the following month |
| WHT certificate issuance to payee | Within 30 days of month-end |
| APIT remittance | Monthly |
| EPF/ETF remittance | Monthly |
| Advance Income Tax installments (CIT) | Quarterly |
| Annual CIT return | Within statutory deadline after year-end |

Each row is a natural fit for a **Custom-type Reminder** (§8) — set once, recurring monthly/quarterly.

---

## 4. Tax Types — master data (List / Create / Edit / Delete)
**List columns:** Name, Category (VAT/WHT/Other), Rate %, Applies To, Effective From, Effective To, Is Default, Status, Actions (Edit / Delete)
**Create/Edit fields:** Name, Category, Rate %, Applies To (Invoice Line Item / Commission Payment / Loan Interest Payment / Other), Effective From, Effective To (nullable), Is Default (per category+applies_to), Is Active
**Delete:** soft-delete only if never used in a real invoice/payment — otherwise set `effective_to` to close it out and let a new rate row take over from that date. Rates are never edited in place once used; a rate change is always a new row.

---

## 5. Migration

```php
// database/migrations/xxxx_create_tax_types_table.php

Schema::create('tax_types', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->enum('category', ['vat', 'wht', 'other']);
    $table->decimal('rate', 5, 2); // e.g. 18.00
    $table->date('effective_from');
    $table->date('effective_to')->nullable();
    $table->string('applies_to'); // invoice_item | commission_payment | loan_interest | other
    $table->boolean('is_default')->default(false);
    $table->boolean('is_active')->default(true);
    $table->softDeletes();
    $table->timestamps();
});
```

Add the resulting columns to the tables that record tax:
```php
// invoice_items
$table->foreignId('tax_type_id')->nullable()->constrained('tax_types');
$table->decimal('tax_rate', 5, 2)->nullable();
$table->decimal('tax_amount', 15, 2)->nullable();

// commission_payments
$table->foreignId('wht_type_id')->nullable()->constrained('tax_types');
$table->decimal('wht_rate', 5, 2)->nullable();
$table->decimal('wht_amount', 15, 2)->nullable();
$table->decimal('net_paid', 15, 2)->nullable();

// loan_interest_schedule
$table->foreignId('wht_type_id')->nullable()->constrained('tax_types');
$table->decimal('wht_rate', 5, 2)->nullable();
$table->decimal('wht_amount', 15, 2)->nullable();
$table->decimal('net_paid', 15, 2)->nullable();
```

---

## 6. Model

```php
// app/Models/TaxType.php

class TaxType extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'category', 'rate', 'effective_from', 'effective_to',
        'applies_to', 'is_default', 'is_active',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_to'   => 'date',
        'rate'           => 'decimal:2',
        'is_default'     => 'boolean',
        'is_active'      => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeAppliesTo($query, string $context)
    {
        return $query->where('applies_to', $context);
    }

    // Resolves the rate that was effective on a given date — used instead
    // of $this->rate directly whenever a historical/back-dated calculation
    // is needed (e.g., re-generating an old invoice's PDF).
    public function rateOnDate(Carbon $date): float
    {
        $historical = static::where('category', $this->category)
            ->where('applies_to', $this->applies_to)
            ->where('effective_from', '<=', $date)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date))
            ->orderByDesc('effective_from')
            ->first();

        return $historical?->rate ?? $this->rate;
    }

    // Used by the controller to block edits/deletes on tax types that have
    // already been applied to real invoices/payments.
    public function hasBeenUsed(): bool
    {
        return InvoiceItem::where('tax_type_id', $this->id)->exists()
            || CommissionPayment::where('wht_type_id', $this->id)->exists()
            || LoanInterestSchedule::where('wht_type_id', $this->id)->exists();
    }
}
```

---

## 7. Calculation Service

```php
// app/Services/TaxCalculator.php

class TaxCalculator
{
    public function vat(float $amount, ?TaxType $taxType = null, ?Carbon $date = null): array
    {
        $taxType ??= TaxType::active()->where('category', 'vat')->where('is_default', true)->first();
        $date ??= now();
        $rate = $taxType->rateOnDate($date);

        $taxAmount = round($amount * ($rate / 100), 2);

        return [
            'tax_type_id' => $taxType->id,
            'rate'        => $rate,
            'tax_amount'  => $taxAmount,
            'total'       => $amount + $taxAmount,
        ];
    }

    public function wht(float $amount, TaxType $taxType, ?Carbon $date = null): array
    {
        $date ??= now();
        $rate = $taxType->rateOnDate($date);
        $withheld = round($amount * ($rate / 100), 2);

        return [
            'tax_type_id'      => $taxType->id,
            'rate'             => $rate,
            'withheld_amount'  => $withheld,
            'net_payable'      => $amount - $withheld, // what actually gets paid out
        ];
    }
}
```

### Usage — Invoice line item (VAT)
```php
$calc = new TaxCalculator();
$result = $calc->vat($lineItem->amount, $invoiceType->defaultVatType, $invoice->invoice_date);

$lineItem->tax_type_id = $result['tax_type_id'];
$lineItem->tax_rate     = $result['rate'];
$lineItem->tax_amount   = $result['tax_amount'];
$lineItem->line_total   = $result['total'];
```

### Usage — Commission / Loan Interest payout (WHT)
```php
$whtType = TaxType::active()->appliesTo('commission_payment')->where('is_default', true)->first();
$result = $calc->wht($commissionPayable->amount, $whtType, now());

CommissionPayment::create([
    'gross_amount' => $commissionPayable->amount,
    'wht_type_id'  => $result['tax_type_id'],
    'wht_rate'     => $result['rate'],
    'wht_amount'   => $result['withheld_amount'],
    'net_paid'     => $result['net_payable'],
]);
```
Same pattern applies to Loan Interest Payments (§10).

---

## 8. Controller — Tax Types CRUD

```php
// app/Http/Controllers/TaxTypeController.php

class TaxTypeController extends Controller
{
    public function index(Request $request)
    {
        $taxTypes = TaxType::query()
            ->when($request->category, fn ($q) => $q->where('category', $request->category))
            ->when($request->applies_to, fn ($q) => $q->where('applies_to', $request->applies_to))
            ->when($request->has('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->orderBy('category')->orderByDesc('effective_from')
            ->paginate(25);

        return response()->json($taxTypes);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'            => 'required|string|max:255',
            'category'        => 'required|in:vat,wht,other',
            'rate'            => 'required|numeric|min:0|max:100',
            'effective_from'  => 'required|date',
            'effective_to'    => 'nullable|date|after:effective_from',
            'applies_to'      => 'required|in:invoice_item,commission_payment,loan_interest,other',
            'is_default'      => 'boolean',
            'is_active'       => 'boolean',
        ]);

        return DB::transaction(function () use ($validated) {
            // Only one default per category+applies_to combination
            if ($validated['is_default'] ?? false) {
                TaxType::where('category', $validated['category'])
                    ->where('applies_to', $validated['applies_to'])
                    ->update(['is_default' => false]);
            }

            $taxType = TaxType::create($validated);

            return response()->json($taxType, 201);
        });
    }

    public function update(Request $request, TaxType $taxType)
    {
        $validated = $request->validate([
            'name'           => 'sometimes|string|max:255',
            'effective_to'   => 'nullable|date|after:effective_from',
            'is_default'     => 'boolean',
            'is_active'      => 'boolean',
        ]);

        // Rate itself is never edited in place once a tax type has been used —
        // close this one out (effective_to) and create a new row instead.
        if ($request->has('rate') && $taxType->hasBeenUsed()) {
            return response()->json([
                'message' => 'This tax rate has already been used in transactions. Set an effective_to date and create a new Tax Type for the new rate instead of editing this one.',
            ], 422);
        }

        DB::transaction(function () use ($validated, $taxType) {
            if ($validated['is_default'] ?? false) {
                TaxType::where('category', $taxType->category)
                    ->where('applies_to', $taxType->applies_to)
                    ->where('id', '!=', $taxType->id)
                    ->update(['is_default' => false]);
            }

            $taxType->update($validated);
        });

        return response()->json($taxType);
    }

    public function destroy(TaxType $taxType)
    {
        if ($taxType->hasBeenUsed()) {
            return response()->json([
                'message' => 'Cannot delete a tax type already referenced by invoices/payments. Deactivate it instead.',
            ], 422);
        }

        $taxType->delete();

        return response()->noContent();
    }
}
```

```php
// routes/api.php
Route::get('/tax-types', [TaxTypeController::class, 'index']);
Route::post('/tax-types', [TaxTypeController::class, 'store']);
Route::put('/tax-types/{taxType}', [TaxTypeController::class, 'update']);
Route::delete('/tax-types/{taxType}', [TaxTypeController::class, 'destroy']);
```

---

## 9. Seeder

```php
// database/seeders/TaxTypeSeeder.php

class TaxTypeSeeder extends Seeder
{
    public function run(): void
    {
        $taxTypes = [
            [
                'name' => 'VAT - Standard',
                'category' => 'vat',
                'rate' => 18.00,
                'effective_from' => '2024-01-01',
                'effective_to' => null,
                'applies_to' => 'invoice_item',
                'is_default' => true,
                'is_active' => true,
            ],
            [
                'name' => 'VAT - Zero-Rated Export',
                'category' => 'vat',
                'rate' => 0.00,
                'effective_from' => '2024-01-01',
                'effective_to' => null,
                'applies_to' => 'invoice_item',
                'is_default' => false,
                'is_active' => true,
            ],
            [
                'name' => 'WHT - Specified Non-Resident Payment',
                'category' => 'wht',
                'rate' => 2.00,
                'effective_from' => '2026-06-08',
                'effective_to' => null,
                'applies_to' => 'commission_payment',
                'is_default' => false,
                'is_active' => true,
            ],
            [
                'name' => 'WHT - Service Fees (Resident Individual)',
                'category' => 'wht',
                'rate' => 5.00,
                'effective_from' => '2026-06-08',
                'effective_to' => null,
                'applies_to' => 'commission_payment',
                'is_default' => true,
                'is_active' => true,
            ],
            [
                'name' => 'WHT - Interest / Rent',
                'category' => 'wht',
                'rate' => 10.00,
                'effective_from' => '2026-06-08',
                'effective_to' => null,
                'applies_to' => 'loan_interest',
                'is_default' => true,
                'is_active' => true,
            ],
            [
                'name' => 'WHT - Royalty / Non-Resident Service',
                'category' => 'wht',
                'rate' => 14.00,
                'effective_from' => '2026-06-08',
                'effective_to' => null,
                'applies_to' => 'commission_payment',
                'is_default' => false,
                'is_active' => true,
            ],
            [
                'name' => 'WHT - Dividend',
                'category' => 'wht',
                'rate' => 15.00,
                'effective_from' => '2026-06-08',
                'effective_to' => null,
                'applies_to' => 'other',
                'is_default' => false,
                'is_active' => true,
            ],
        ];

        foreach ($taxTypes as $taxType) {
            TaxType::updateOrCreate(
                ['name' => $taxType['name']],
                $taxType
            );
        }
    }
}
```

```php
// database/seeders/DatabaseSeeder.php
public function run(): void
{
    $this->call([
        TaxTypeSeeder::class,
        // ...other seeders
    ]);
}
```

Run it with:
```
php artisan db:seed --class=TaxTypeSeeder
```

**Note:** historical WHT rates (pre-June 2026 circular — e.g., interest at 5% before rising to 10% on April 1, 2025) aren't seeded here, since `effective_from`/`effective_to` on the live rates is enough going forward. Backfill older rows only if you need to reproduce historical invoices/payments exactly as they were calculated at the time.