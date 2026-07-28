# Employee & Server Cost Allocation

Lets you record how much was spent **per employee** or **per server** against a project — as a costing fact, not a bank transaction. Entry is deliberately simple: a quick-add form available both inside the **Project Single View** and from a **global sidebar widget**, so logging a cost never requires leaving whatever screen you're on.

---

## 1. Where it lives

- **Project Detail Page** → a "Cost Allocation" tab, listing all allocations for that project, with an inline "+ Add" form at the top (no separate page navigation).
- **Global Sidebar** → a persistent "Quick Add Cost" widget (same sidebar area used for Draft Invoices and Payment Milestones) — lets you log a cost against *any* project without opening it first. Useful when you're just paying a server bill and want to log it in 10 seconds.

Both use the same underlying form; the sidebar version just pre-selects nothing (you pick the project), while the project-view version pre-fills the project.

---

## 2. List screen (within a project, or global "All Cost Allocations" view)
**Columns:** Date/Period, Type (Employee/Server/Other), Employee or Server Name, Amount, Currency, Source (Manual/Synced), Notes, Actions (Edit / Delete)
**Filters:** Date range, Type, Project, Employee, Server, Tag

---

## 3. Quick-Add Form — fields (project view & sidebar, identical)
- Project *(dropdown, required — pre-filled if opened from Project view)*
- Type *(radio: Employee / Server / Other Cost Center — required)*
  - **Employee**: Employee *(the **Employee Selector** component — see §6 — picks from employees synced into this system's own `employees` table)*
  - **Server**: Server *(dropdown — from a small internal Servers master list, see §5)*
  - **Other**: free-text Cost Center Name
- Period *(month picker, e.g. "July 2026" — or a specific Date for one-off costs)*
- Amount *(required — uses the Amount Input component)*
- Currency *(dropdown, defaults to project currency)*
- Notes (optional)

**Delete:** soft-delete; keeps history for reports that already ran against it.

---

## 4. Why this is NOT a Transaction

- It doesn't touch a bank account or petty cash balance — the money already moved through payroll or a hosting invoice elsewhere.
- No paid/pending workflow — it's a cost *fact* recorded for reporting, not a cash event.
- Keeping it separate means your Cash Flow / bank reconciliation reports stay accurate (no double-counting), while Project Profitability reports can still pull true cost.

---

## 5. Data model & migration

```php
// database/migrations/xxxx_create_cost_allocations_table.php

Schema::create('cost_allocations', function (Blueprint $table) {
    $table->id();
    $table->foreignId('project_id')->constrained();
    $table->enum('type', ['employee', 'server', 'other']);
    $table->foreignId('employee_id')->nullable()->constrained('employees');
    $table->foreignId('server_id')->nullable()->constrained('servers');
    $table->string('cost_center_name')->nullable(); // used when type = other
    $table->date('period_start');
    $table->date('period_end')->nullable();        // null = single-date entry
    $table->decimal('amount', 15, 2);
    $table->string('currency', 3);
    $table->enum('source', ['manual', 'synced'])->default('manual');
    $table->text('notes')->nullable();
    $table->foreignId('created_by')->constrained('users');
    $table->softDeletes();
    $table->timestamps();
});

Schema::create('servers', function (Blueprint $table) {
    $table->id();
    $table->string('name');           // e.g. "DigitalOcean - Apptimus API"
    $table->string('provider')->nullable();
    $table->string('reference')->nullable(); // droplet ID, instance ID, etc.
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

---

## 6. Employee Directory Sync (from your existing HR system)

Rather than re-entering employee data by hand, the system pulls it from your existing employee/attendance system's API — configured once, synced on demand (or on a schedule).

### 6.1 API Integration Settings — screen
**Fields:**
- Integration Name *(e.g., "HR System — Employee Directory")*
- API URL *(required)*
- HTTP Method *(dropdown: GET / POST — required)*
- Bearer Token *(required, stored encrypted)*
- Response Data Path *(optional — e.g., `data` if the employee array is nested under a `data` key rather than being the raw response)*
- Last Synced At *(read-only, updated after every sync)*
- Last Sync Status *(Success / Failed, with error message if failed)*

**Actions:** Save (validates and stores the config), **Sync Now** (triggers an immediate fetch), and — optionally — a schedule toggle ("Auto-sync daily at [time]").

### 6.2 Employees table (local mirror)
Mirrors the fields your API already returns:
```php
Schema::create('employees', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('external_id')->unique(); // "id" from the source API
    $table->string('employee_code')->nullable();
    $table->string('first_name');
    $table->string('last_name');
    $table->string('full_name');
    $table->string('personal_email')->nullable();
    $table->string('mobile_phone')->nullable();
    $table->string('profile_picture_url')->nullable();
    $table->string('status')->default('active');       // active / inactive
    $table->string('user_type')->nullable();
    $table->string('job_position')->nullable();
    $table->string('role')->nullable();
    $table->timestamp('synced_at')->nullable();
    $table->timestamps();
});

Schema::create('api_integrations', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('url');
    $table->string('method')->default('GET');
    $table->text('bearer_token'); // encrypted cast
    $table->string('response_path')->nullable();
    $table->timestamp('last_synced_at')->nullable();
    $table->string('last_sync_status')->nullable();
    $table->text('last_sync_error')->nullable();
    $table->timestamps();
});
```

### 6.3 Employee model
```php
// app/Models/Employee.php
class Employee extends Model
{
    protected $fillable = [
        'external_id', 'employee_code', 'first_name', 'last_name', 'full_name',
        'personal_email', 'mobile_phone', 'profile_picture_url',
        'status', 'user_type', 'job_position', 'role', 'synced_at',
    ];

    public function costAllocations()
    {
        return $this->hasMany(CostAllocation::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
```

### 6.4 API Integration model (encrypted token)
```php
// app/Models/ApiIntegration.php
class ApiIntegration extends Model
{
    protected $fillable = ['name', 'url', 'method', 'bearer_token', 'response_path'];

    protected $casts = [
        'bearer_token' => 'encrypted',
    ];
}
```

### 6.5 Sync job — fetch & upsert
```php
// app/Jobs/SyncEmployeesJob.php
class SyncEmployeesJob implements ShouldQueue
{
    public function __construct(protected ApiIntegration $integration) {}

    public function handle(): void
    {
        try {
            $response = Http::withToken($this->integration->bearer_token)
                ->{strtolower($this->integration->method)}($this->integration->url);

            $response->throw();

            $employees = $this->integration->response_path
                ? data_get($response->json(), $this->integration->response_path)
                : $response->json();

            foreach ($employees as $row) {
                Employee::updateOrCreate(
                    ['external_id' => $row['id']],
                    [
                        'employee_code'        => $row['employee_code'] ?? null,
                        'first_name'           => $row['first_name'],
                        'last_name'            => $row['last_name'],
                        'full_name'            => $row['full_name'],
                        'personal_email'       => $row['personal_email'] ?? null,
                        'mobile_phone'         => $row['mobile_phone'] ?? null,
                        'profile_picture_url'  => $row['profile_picture_url'] ?? null,
                        'status'               => $row['status'] ?? 'active',
                        'user_type'            => $row['user_type'] ?? null,
                        'job_position'         => $row['job_position'] ?? null,
                        'role'                 => $row['role'] ?? null,
                        'synced_at'            => now(),
                    ]
                );
            }

            $this->integration->update([
                'last_synced_at'    => now(),
                'last_sync_status'  => 'success',
                'last_sync_error'   => null,
            ]);
        } catch (\Throwable $e) {
            $this->integration->update([
                'last_sync_status' => 'failed',
                'last_sync_error'  => $e->getMessage(),
            ]);
        }
    }
}
```

### 6.6 Controller — save config + trigger sync
```php
// app/Http/Controllers/ApiIntegrationController.php
class ApiIntegrationController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'          => 'required|string',
            'url'           => 'required|url',
            'method'        => 'required|in:GET,POST',
            'bearer_token'  => 'required|string',
            'response_path' => 'nullable|string',
        ]);

        $integration = ApiIntegration::updateOrCreate(
            ['name' => $validated['name']],
            $validated
        );

        return response()->json($integration, 201);
    }

    public function sync(ApiIntegration $apiIntegration)
    {
        SyncEmployeesJob::dispatchSync($apiIntegration); // dispatchSync = run immediately for the "Sync Now" button
        $apiIntegration->refresh();

        return response()->json([
            'status'  => $apiIntegration->last_sync_status,
            'error'   => $apiIntegration->last_sync_error,
            'synced_at' => $apiIntegration->last_synced_at,
        ]);
    }
}
```

```php
// routes/api.php
Route::post('/api-integrations', [ApiIntegrationController::class, 'store']);
Route::post('/api-integrations/{apiIntegration}/sync', [ApiIntegrationController::class, 'sync']);
```

For **Auto-sync daily**, schedule the same job in `routes/console.php` (or `App\Console\Kernel` on older Laravel versions):
```php
Schedule::call(function () {
    ApiIntegration::each(fn ($integration) => SyncEmployeesJob::dispatch($integration));
})->dailyAt('02:00');
```

### 6.7 Employee Selector component (used in the Cost Allocation screen)

Same popup-with-search pattern as the Master Data Selector (§18 in the shared components spec), but tailored to show employee-specific detail (photo, code, job position):

```html
<!-- resources/views/components/employee-selector.blade.php -->
<div x-data="{
        open: false,
        search: '',
        selected: null,
        employees: [],
        fetchEmployees() {
            fetch(`/api/employees?search=${this.search}&status=active`)
                .then(r => r.json())
                .then(data => this.employees = data.data);
        }
     }" x-init="fetchEmployees()">

    <button @click="open = true" class="selector-trigger">
        <template x-if="selected">
            <span>
                <img :src="selected.profile_picture_url ?? '/img/avatar-placeholder.png'" class="avatar-sm">
                <span x-text="selected.full_name"></span>
                <small x-text="selected.job_position"></small>
            </span>
        </template>
        <template x-if="!selected">Select employee...</template>
    </button>

    <div x-show="open" class="selector-popup">
        <input type="text" x-model="search" @input.debounce.300ms="fetchEmployees()"
               placeholder="Search by name or employee code...">

        <ul class="selector-results">
            <template x-for="emp in employees" :key="emp.id">
                <li @click="selected = emp; $dispatch('employee-selected', emp.id); open = false">
                    <img :src="emp.profile_picture_url ?? '/img/avatar-placeholder.png'" class="avatar-sm">
                    <div>
                        <div x-text="emp.full_name"></div>
                        <small x-text="`${emp.employee_code} · ${emp.job_position}`"></small>
                    </div>
                </li>
            </template>
        </ul>
    </div>
</div>
```

Backing endpoint:
```php
// GET /api/employees?search=&status=active
public function index(Request $request)
{
    return Employee::query()
        ->when($request->status, fn ($q) => $q->where('status', $request->status))
        ->when($request->search, fn ($q) => $q->where(function ($q) use ($request) {
            $q->where('full_name', 'like', "%{$request->search}%")
              ->orWhere('employee_code', 'like', "%{$request->search}%");
        }))
        ->orderBy('full_name')
        ->paginate(20);
}
```

This same `<x-employee-selector>` component drops directly into the Cost Allocation Quick-Add form (§3) in place of a plain dropdown — selecting an employee sets `employee_id` on the form, which the `store()` endpoint from §8 saves straight onto `cost_allocations.employee_id`.

---

## 7. Model

```php
// app/Models/CostAllocation.php

class CostAllocation extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'project_id', 'type', 'employee_id', 'server_id',
        'cost_center_name', 'period_start', 'period_end',
        'amount', 'currency', 'source', 'notes', 'created_by',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end'   => 'date',
        'amount'       => 'decimal:2',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function server()
    {
        return $this->belongsTo(Server::class);
    }

    // Employee is now a local model (synced from the HR system, §6),
    // so this is a plain relationship — no external API call needed.
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
```

---

## 8. Controller (quick-add used by both Project view and Sidebar)

```php
// app/Http/Controllers/CostAllocationController.php

class CostAllocationController extends Controller
{
    public function index(Request $request)
    {
        $allocations = CostAllocation::query()
            ->when($request->project_id, fn ($q) => $q->where('project_id', $request->project_id))
            ->when($request->type, fn ($q) => $q->where('type', $request->type))
            ->when($request->from && $request->to, fn ($q) => $q->whereBetween('period_start', [$request->from, $request->to]))
            ->latest('period_start')
            ->paginate(25);

        return response()->json($allocations);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'project_id'   => 'required|exists:projects,id',
            'type'         => 'required|in:employee,server,other',
            'employee_id'  => 'required_if:type,employee|nullable|exists:employees,id',
            'server_id'    => 'required_if:type,server|nullable|exists:servers,id',
            'cost_center_name' => 'required_if:type,other|nullable|string',
            'period_start' => 'required|date',
            'period_end'   => 'nullable|date|after_or_equal:period_start',
            'amount'       => 'required|numeric|min:0',
            'currency'     => 'required|string|size:3',
            'notes'        => 'nullable|string',
        ]);

        $allocation = CostAllocation::create([
            ...$validated,
            'source'     => 'manual',
            'created_by' => auth()->id(),
        ]);

        return response()->json($allocation, 201);
    }

    public function destroy(CostAllocation $costAllocation)
    {
        $this->authorize('delete', $costAllocation);
        $costAllocation->delete();

        return response()->noContent();
    }
}
```

```php
// routes/api.php
Route::get('/cost-allocations', [CostAllocationController::class, 'index']);
Route::post('/cost-allocations', [CostAllocationController::class, 'store']);
Route::delete('/cost-allocations/{costAllocation}', [CostAllocationController::class, 'destroy']);
```

---

## 9. Sidebar quick-add — minimal Blade/Alpine example

```html
<!-- resources/views/components/sidebar/quick-add-cost.blade.php -->
<div x-data="{
        open: false,
        type: 'employee',
        project_id: null,
        amount: null,
        currency: '{{ auth()->user()->default_currency ?? 'LKR' }}',
        submit() {
            fetch('/api/cost-allocations', {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}'},
                body: JSON.stringify({
                    project_id: this.project_id,
                    type: this.type,
                    amount: this.amount,
                    currency: this.currency,
                    period_start: new Date().toISOString().slice(0,10),
                }),
            }).then(() => { this.open = false; $dispatch('cost-allocation-added'); });
        }
     }">
    <button @click="open = !open" class="sidebar-widget-toggle">+ Quick Add Cost</button>

    <div x-show="open" class="sidebar-widget-panel">
        <x-master-data-selector entity="project" x-model="project_id" placeholder="Select project..." />

        <select x-model="type">
            <option value="employee">Employee</option>
            <option value="server">Server</option>
            <option value="other">Other</option>
        </select>

        <x-amount-input x-model:amount="amount" x-model:currency="currency" />

        <button @click="submit()">Save</button>
    </div>
</div>
```

This reuses the **Master Data Selector** and **Amount Input** components already defined in the shared components spec (§18) — the sidebar widget is just a thin composition of them plus the type radio, so it stays genuinely quick to fill.

---

## 10. Budget tie-in

A Budget (§9) scoped to a server or an employee cost pool can pull its "actual spent" from this table instead of `transactions`:

```php
$actual = CostAllocation::where('server_id', $budget->server_id)
    ->whereBetween('period_start', [$request->from, $request->to])
    ->sum('amount');
```

---

## 11. Project Profitability tie-in

```php
$trueCost = CostAllocation::where('project_id', $project->id)
    ->whereBetween('period_start', [$from, $to])
    ->sum('amount');

$profitability = $project->invoices()->sum('grand_total') - $trueCost;
```

---

## 12. Extra suggestions
- **Sync from timesheets**: a scheduled job that reads hours-per-project from your existing attendance/timesheet system's API, multiplies by a stored hourly/monthly rate (kept in a small `employee_rates` table here, since that likely doesn't belong in your attendance system), and auto-creates `cost_allocations` rows with `source = 'synced'` — manual entries stay possible for corrections or one-off costs.
- **Server cost import**: allow uploading a monthly hosting invoice (CSV/PDF) to bulk-create server allocations instead of one row at a time.
- **Sidebar badge**: optionally show a small counter of "costs logged this month" as a soft reminder if a period is closing without server/employee costs recorded — not a hard requirement, just a nudge.