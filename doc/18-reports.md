# Reports for a Software Company (~1,000 employees, ~3,000 projects)

At this scale, reporting needs go well beyond finance — you're coordinating people, projects, clients, and infrastructure across many teams. Below is a full catalog organized by department, so each team gets what it actually needs instead of one giant report trying to cover everything.

Each section also includes a **Build approach** note — the tables involved and how you'd actually query/aggregate it in Laravel — so this doubles as an implementation guide, not just a wishlist.

---

## 0. How These Reports Get Built (General Pattern)

Every report in this document follows the same shape, regardless of domain:

**1. Route + Controller**
```php
Route::get('/reports/pnl', [ReportController::class, 'profitLoss']);
```
One controller method per report (or a `ReportController` per domain — `FinanceReportController`, `HrReportController`, etc. once the app grows). The method reads filters from the request (date range, company, department, tag), runs the aggregation, and returns either a Blade view (with charts via Chart.js/ApexCharts) or JSON (if the frontend is Vue/React driven).

**2. Filtering pattern** — every report applies the same conditional-filter style so query methods stay reusable:
```php
$query = Transaction::query()
    ->whereBetween('date', [$request->date('from'), $request->date('to')])
    ->when($request->company_id, fn($q) => $q->where('company_id', $request->company_id))
    ->when($request->department_id, fn($q) => $q->where('department_id', $request->department_id))
    ->when($request->tag, fn($q) => $q->whereHas('tags', fn($t) => $t->where('name', $request->tag)));
```

**3. Aggregation** — mostly `groupBy()` + `SUM`/`COUNT`/`AVG`, or Eloquent relationship aggregates (`withSum`, `withCount`) when reporting on a parent record (e.g., projects with their total invoiced amount):
```php
Project::withSum('invoices as invoiced_total', 'grand_total')
       ->withSum('payments as collected_total', 'amount')
       ->get();
```

**4. Performance at this scale (1,000 employees / 3,000 projects matters here)**
- Raw on-the-fly aggregation across millions of transaction/ticket/attendance rows will get slow. For heavy cross-company reports (Executive Dashboard, company-wide P&L, utilization), don't compute live — run a **nightly scheduled job** (`php artisan schedule`) that pre-aggregates into summary tables (e.g., `daily_project_summary`, `monthly_department_pnl`), and have the report read from those instead. Live queries stay fine for narrower reports (single project, single client).
- Add DB indexes on every column used in `WHERE`/`GROUP BY` for these reports up front: `date`, `company_id`, `department_id`, `project_id`, `status`.
- Cache expensive, slow-changing aggregates with `Cache::remember('report-key', now()->addHours(1), fn() => ...)`.

**5. Export** — `maatwebsite/laravel-excel` for Excel, `barryvdh/laravel-dompdf` for PDF, both fed the same filtered dataset the on-screen report used, so what you see is what you export.

**6. Charts** — pass aggregated arrays to a JS chart library (Chart.js/ApexCharts) via the Blade view or an API endpoint if using a JS frontend.

The domain-specific notes below assume this same pattern — they only call out what's different: which tables, which specific aggregation logic, and whether the underlying module already exists in this spec or would need to be built first.

---

## 1. Finance & Accounting
*(Detailed already in the Finance System spec — summarized here for completeness)*

| Report | What it shows |
|---|---|
| Monthly/Quarterly P&L | Income vs expense by category, department, company |
| Balance Sheet | Assets, liabilities, equity snapshot |
| Cash Flow Statement | Cash in/out, operating vs financing |
| Budget vs Actual | Allocated vs spent, by department/server/project |
| Accounts Receivable Aging | Overdue invoices by client, 0–30/31–60/61–90/90+ |
| Accounts Payable Aging | What the company owes, by vendor/loan |
| Project Profitability | Revenue − cost per project, margin % |
| Revenue by Client / Region | Where income concentrates |
| Payroll Cost Summary | Total payroll by department/month |
| Tax/VAT Summary | Taxable income/expense for filing |

**Filters:** Date Range, Company, Department, Client, Project, Currency (Native/Base rollup), Tag

**Build approach:** Every table here maps directly to entities already in the Finance spec (`transactions`, `invoices`, `budgets`, `loans` — see `20-data-model.md`). Example — Monthly P&L:
```php
public function profitLoss(Request $request)
{
    $range = [$request->date('from'), $request->date('to')];

    $income = Transaction::where('type', 'income')
        ->whereBetween('date', $range)
        ->when($request->company_id, fn($q) => $q->where('company_id', $request->company_id))
        ->join('categories', 'transactions.category_id', '=', 'categories.id')
        ->selectRaw('categories.name as category, SUM(amount) as total')
        ->groupBy('categories.name')->get();

    $expense = Transaction::where('type', 'expense')
        ->whereBetween('date', $range)
        ->join('categories', 'transactions.category_id', '=', 'categories.id')
        ->selectRaw('categories.name as category, SUM(amount) as total')
        ->groupBy('categories.name')->get();

    return view('reports.pnl', [
        'income' => $income, 'expense' => $expense,
        'net' => $income->sum('total') - $expense->sum('total'),
    ]);
}
```
Budget vs Actual and AR Aging follow the same shape — `Budget::withSum('transactions as actual', 'amount')` for the former; grouping `Invoice::whereIn('status', [...])` by days-overdue buckets for the latter.

---

## 2. HR & Workforce

At 1,000 employees, HR reporting is a major category on its own.

| Report | What it shows |
|---|---|
| Headcount Report | Total employees by department, role, location, employment type |
| Attrition / Turnover Rate | Monthly/quarterly attrition %, voluntary vs involuntary, by department |
| Hiring Funnel / Time-to-Hire | Open positions, candidates per stage, average days to fill |
| Attendance & Leave Summary | Present/absent trends, leave balances, leave type breakdown |
| Overtime Report | Hours worked beyond standard, by department — cost implication |
| Bench Strength Report | Employees currently unassigned/available vs allocated to projects (critical at 3,000 projects) |
| Utilization Rate | Billable hours ÷ total available hours, by employee/team/department |
| Skill Inventory / Skill Gap | What skills exist vs what upcoming projects need |
| Performance Review Status | Completion % of review cycles, rating distribution |
| Training Completion | Mandatory/optional training completion by employee/department |
| Compensation Band Report | Salary distribution by band/role (HR/leadership only, access-restricted) |
| Employee Satisfaction / eNPS Trend | Survey results over time |

**Filters:** Date Range (joining date/period for cohort reports), Company, Department, Location, Role/Designation, Employment Type

**Build approach:** ⚠️ This needs an **HR module** that isn't part of the Finance spec yet — you'd need core tables like `employees`, `attendance`, `leave_requests`, `leave_balances`, `job_openings`, `candidates`, `performance_reviews`, `trainings`. Once those exist, the reports follow the same pattern as Finance:
- **Headcount / Attrition** — `Employee::where('status', 'active')->count()` grouped by department; attrition rate = `terminated_count / average_headcount` for the period.
- **Utilization Rate** — needs a `timesheets` or `time_entries` table (billable hours logged against a `project_id`); `SUM(billable_hours) / SUM(available_hours)` per employee/period.
- **Bench Strength** — employees with zero active `project_assignments` rows for the current date range; a simple `whereDoesntHave('activeAssignments')` query once a `project_resource_assignments` table exists (see §3 below — the same table feeds both HR bench reporting and Project resource allocation).
- Given 1,000 employees, attendance/timesheet data grows fast — this is one of the first candidates for the nightly pre-aggregation pattern from §0.

---

## 3. Project & Delivery Management

With 3,000 projects, delivery visibility is critical.

| Report | What it shows |
|---|---|
| Project Status Dashboard | All active projects: status, health (green/amber/red), % complete |
| Project Health/Risk Report | At-risk projects flagged by budget overrun, timeline slip, or unresolved blockers |
| Milestone Completion Report | On-time vs delayed milestones, aggregated across projects |
| Resource Allocation Report | Who's assigned to what, at what % capacity, across all active projects |
| Overallocation/Conflict Report | Employees assigned beyond 100% capacity across overlapping projects |
| Delivery Timeline Variance | Planned vs actual delivery dates, aggregated |
| Change Request Volume & Impact | CR count, approved vs rejected, cumulative cost/time impact per project |
| Sprint/Iteration Velocity (Agile teams) | Story points or tasks completed per sprint, trend over time |
| Project Type/Category Breakdown | Fixed-bid vs time & material vs retainer, count and revenue by type |
| Client Escalation Report | Open escalations by project/client, severity, resolution time |

**Filters:** Date Range (project start/milestone date), Company, Department, Client, Project Type (Fixed-bid/T&M/Retainer), Project Status, Tag

**Build approach:** Partially covered by the Finance spec's `projects` table; the resourcing-specific reports need one addition — a `project_resource_assignments` table (`employee_id`, `project_id`, `allocation_percent`, `start_date`, `end_date`). Example — Overallocation Report:
```php
public function overallocatedEmployees(Request $request)
{
    return ProjectResourceAssignment::whereDate('start_date', '<=', now())
        ->where(fn($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', now()))
        ->selectRaw('employee_id, SUM(allocation_percent) as total_allocation')
        ->groupBy('employee_id')
        ->having('total_allocation', '>', 100)
        ->with('employee')
        ->get();
}
```
Project Health (green/amber/red) is usually a computed flag, not stored: e.g., red if `budget_actual > budget_allocated * 1.1` OR `days_until_due < 0` — computed in a query or a scheduled job that writes the flag onto the project row nightly, so the dashboard tile just reads a column instead of recalculating live for all 3,000 projects on every page load.

---

## 4. Sales & Business Development

| Report | What it shows |
|---|---|
| Sales Pipeline / Funnel | Leads by stage, conversion rate stage-to-stage |
| Win/Loss Report | Deals won vs lost, reasons, by sales rep/region |
| New Business vs Renewal Revenue | Split of revenue from new clients vs existing client renewals/expansions |
| Proposal-to-Close Time | Average days from proposal sent to contract signed |
| Sales by Rep/Team | Revenue attributed per salesperson, quota attainment |
| Client Acquisition Cost (CAC) | Cost to acquire a new client vs revenue generated |

**Filters:** Date Range (deal created/closed date), Sales Rep/Team, Region, Deal Stage, Client Type (New/Existing)

**Build approach:** ⚠️ Needs a **CRM module** — `leads`, `deals` (with a `stage` enum/FK to `pipeline_stages`), `deal_activities`. Pipeline/funnel report is a `groupBy('stage')->count()`; Win/Loss is `groupBy('status')` where status is Won/Lost, with a `lost_reason` column for the reasons breakdown. If Sales and Delivery are meant to connect (a Won deal becomes a Project), add `deals.converted_project_id` so revenue can be traced from lead all the way to invoiced amount.

---

## 5. Client & Account Management

| Report | What it shows |
|---|---|
| Client Portfolio Overview | All active clients, total projects, total revenue, relationship duration |
| Client Health Score | Composite of payment timeliness, escalations, satisfaction, renewal likelihood |
| Client Revenue Concentration | % of total revenue from top N clients (risk of over-reliance on few clients) |
| Contract Renewal Calendar | Upcoming contract/retainer expirations needing action |
| Client Satisfaction (CSAT/NPS) Trend | Survey scores over time, by client/account manager |
| Upsell/Cross-sell Opportunity Report | Clients with room for additional services based on usage/engagement patterns |

**Filters:** Date Range (relationship start/renewal date), Account Manager, Client Tier/Segment, Industry, Tag

**Build approach:** Builds directly on the **Parties** table (§1.7 in the Finance spec, `type = Client`) plus their linked Projects/Invoices — no new module needed for the basics. Client Health Score is a computed weighted score, e.g.:
```php
$healthScore = (
    ($paymentTimelinessScore * 0.4) +
    ($escalationScore * 0.3) +
    ($satisfactionScore * 0.3)
);
```
where each sub-score is normalized 0–100 from its own source (payment timeliness from invoice due-vs-paid dates, escalations from the Support module count, satisfaction from a CSAT survey table). Revenue Concentration is a simple `Invoice::groupBy('client_id')->sum('grand_total')` ordered descending, expressed as % of the period total.

---

## 6. Support & Maintenance

For a company handling post-delivery support at this scale.

| Report | What it shows |
|---|---|
| Ticket Volume Report | Tickets raised/resolved, by client/project/priority |
| SLA Compliance Report | % of tickets resolved within SLA, breach count and reasons |
| First Response / Resolution Time | Average and median times, by priority level |
| Recurring Issue Report | Tickets grouped by root cause, flags systemic problems |
| Support Load by Team/Agent | Ticket distribution, helps spot overload |

**Filters:** Date Range (ticket raised/resolved date), Client, Project, Priority, Ticket Status, Support Agent/Team

**Build approach:** ⚠️ Needs a **Ticketing module** — `tickets` (`priority`, `status`, `sla_due_at`, `resolved_at`, `project_id`, `assigned_to`). SLA Compliance is `whereColumn('resolved_at', '<=', 'sla_due_at')->count() / total_count`. If you already use an external help-desk tool (Zendesk, Freshdesk), it's usually cheaper to pull this via their API/webhook into a local `tickets` mirror table nightly rather than rebuild ticketing from scratch.

---

## 7. IT / DevOps / Infrastructure

| Report | What it shows |
|---|---|
| Server/Cloud Cost by Project | Infrastructure spend attributed per project/client (ties into Budget module) |
| Uptime/Downtime Report | Service availability per environment/client |
| Deployment Frequency & Failure Rate | DevOps health metrics (DORA-style: deploy frequency, change failure rate, MTTR) |
| Security Incident Log | Incidents, severity, resolution status |
| License/Subscription Usage | Software licenses in use vs paid-for seats — cost optimization |

**Filters:** Date Range, Project, Client, Environment (Prod/Staging/Dev), Cloud Provider/Vendor

**Build approach:** Server/Cloud Cost by Project reuses the Finance spec's **Budget module** directly (§9 — a "DigitalOcean Server — ProjectX" budget with transactions tagged to it; the report is just `Budget::where('scope_type', 'project')->withSum('transactions as actual', 'amount')->get()`). Uptime, deployment frequency, and security incidents need integration with your actual infra tooling (CloudWatch/Datadog/CI pipeline webhooks feeding a local `deployments` and `incidents` table) — this is more of a data-pipeline task than a Laravel CRUD module.

---

## 8. Quality & Compliance

| Report | What it shows |
|---|---|
| QA Defect Report | Bugs found by severity, by project, resolution time |
| Code Review / Audit Coverage | % of code reviewed, audit findings if applicable |
| Compliance Checklist Status | For clients requiring ISO/SOC2/GDPR-type compliance — status per requirement |
| Vendor/Third-Party Risk Report | Status of vendor contracts, data-processing agreements, renewal dates |

**Filters:** Date Range, Project, Client, Severity, Compliance Standard (ISO/SOC2/GDPR)

**Build approach:** ⚠️ Needs a lightweight **QA/Compliance module** — `defects` (severity, status, project_id) if not already tracked in an external tool like Jira (in which case, mirror via API sync rather than duplicate); `compliance_checklist_items` (standard, requirement, status, evidence_link) for the checklist-style reports — these are simple CRUD tables, no complex aggregation needed, mostly `groupBy('status')` counts.

---

## 9. Executive / Leadership Dashboard

A roll-up view combining the above for company-wide decision-making — this is the one screen a CEO/COO actually lives in day-to-day.

| Report | What it shows |
|---|---|
| Company-wide Revenue & Profitability Trend | Monthly/quarterly, company-wide and by sub-company (Apptimus/Joboro/Placements) |
| Headcount vs Revenue per Employee | Efficiency metric — revenue generated per employee, trend over time |
| Project Portfolio Health Summary | Count of projects by health status (green/amber/red) across the whole portfolio |
| Utilization vs Bench Trend | Company-wide billable utilization %, bench cost impact |
| Top 10 Clients by Revenue | Concentration risk at a glance |
| Cash Position & Runway | Current cash, burn rate, runway in months (especially relevant alongside the Loans module) |
| Department/Sub-company Comparison | Side-by-side performance across Apptimus, Joboro, Placements |
| Strategic KPI Scorecard | Whatever 5–8 metrics leadership has chosen to track quarter over quarter |

**Filters:** Date Range (with MoM/QoQ/YoY comparison toggle), Company/Sub-company, Department, Currency (Base rollup)

**Build approach:** This is a **read-only aggregation layer over everything above** — no new tables of its own beyond maybe a `kpi_scorecard_targets` table for the Strategic KPI Scorecard (target values leadership sets per metric per quarter, compared against the actual computed value). Given it queries across Finance + HR + Project data simultaneously and needs to load fast for daily executive use, this is the single strongest candidate for the nightly-summary-table pattern from §0 — e.g., a `daily_company_kpis` table populated by a scheduled job, with the dashboard controller doing nothing more than `DailyCompanyKpi::whereBetween('date', $range)->get()`.

---

## 10. Cross-cutting reporting principles (applies to all of the above)
- **Date range filter is mandatory on every report** — with quick presets (Today, This Week, This Month, This Quarter, This Year, Custom) plus a period-comparison toggle (vs previous period / vs same period last year) wherever trend matters.
- **Filter + drill-down** — every summary table/chart supports clicking through to the underlying detail list, pre-filtered to match.
- **Role-based access** — compensation, individual performance, and financial detail reports restricted to appropriate roles; filters themselves can be scope-limited (e.g., a Department Head's Date Range filter still only ever returns their own department's data).
- **Export** to PDF/Excel on every report, respecting whatever filters/date range are currently applied.
- **Scheduled delivery** — key reports (e.g., weekly project health, monthly P&L) auto-emailed to relevant stakeholders on a fixed date range (e.g., "last 7 days," "last calendar month") rather than requiring manual login to check.
- **Consistent filter bar** — Date Range, Company, Department, Tag (and report-specific filters listed above) reused across every report screen so users aren't relearning UI per report.