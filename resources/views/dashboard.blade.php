@extends('layouts.app')
@section('title', 'Finance System Dashboard')

@section('content')
<style>
    .dashboard-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 1rem;
        margin-bottom: 2rem;
    }
    .dashboard-title {
        font-size: 1.75rem;
        font-weight: 700;
        color: var(--text-heading);
    }
    .filter-bar {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 1rem 1.5rem;
        margin-bottom: 2rem;
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 1rem;
        box-shadow: var(--shadow-sm);
    }
    .filter-group {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }
    .filter-label {
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .filter-select, .filter-input {
        background: var(--bg-page);
        border: 1px solid var(--border);
        border-radius: 8px;
        color: var(--text-main);
        padding: 0.5rem 1rem;
        font-family: inherit;
        font-size: 0.85rem;
        min-width: 140px;
    }
    .filter-select:focus, .filter-input:focus {
        outline: none;
        border-color: var(--primary);
    }
    .kpi-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 1.25rem;
        margin-bottom: 2.5rem;
    }
    .kpi-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 16px;
        padding: 1.25rem;
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        transition: transform 0.2s, box-shadow 0.2s;
        cursor: pointer;
        position: relative;
        overflow: hidden;
    }
    .kpi-card:hover {
        transform: translateY(-3px);
        box-shadow: var(--shadow-card);
    }
    .kpi-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 4px;
        background: var(--border-light);
    }
    .kpi-card.kpi-primary::before { background: var(--primary-gradient); }
    .kpi-card.kpi-success::before { background: var(--success); }
    .kpi-card.kpi-danger::before { background: var(--danger); }
    .kpi-card.kpi-warning::before { background: var(--warning); }
    
    .kpi-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        color: var(--text-muted);
    }
    .kpi-icon {
        font-size: 1.5rem;
        color: var(--text-light);
    }
    .kpi-title {
        font-size: 0.85rem;
        font-weight: 500;
    }
    .kpi-value {
        font-size: 1.6rem;
        font-weight: 700;
        color: var(--text-heading);
        margin: 0.25rem 0;
    }
    .kpi-meta {
        font-size: 0.75rem;
        color: var(--text-muted);
    }
    .charts-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2.5rem;
    }
    .chart-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 16px;
        padding: 1.5rem;
        box-shadow: var(--shadow-sm);
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }
    .chart-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .chart-title {
        font-size: 1rem;
        font-weight: 600;
        color: var(--text-heading);
    }
    .tables-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
        gap: 1.5rem;
    }
    .table-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 16px;
        padding: 1.5rem;
        box-shadow: var(--shadow-sm);
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }
    .table-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid var(--border-light);
        padding-bottom: 0.75rem;
    }
    .table-title {
        font-size: 1rem;
        font-weight: 600;
        color: var(--text-heading);
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .table-action-link {
        font-size: 0.85rem;
        color: var(--primary);
        font-weight: 500;
    }
    .table-action-link:hover {
        text-decoration: underline;
    }

    @media (max-width: 768px) {
        .dashboard-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 0.5rem;
            margin-bottom: 1.25rem;
        }
        .dashboard-title {
            font-size: 1.4rem;
        }
        .filter-bar {
            padding: 1rem;
            flex-direction: column;
            align-items: stretch;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
        }
        .filter-group {
            width: 100%;
        }
        .filter-select, .filter-input {
            width: 100%;
            min-width: 0;
            font-size: 16px;
        }
        .kpi-grid {
            grid-template-columns: 1fr;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .charts-grid {
            grid-template-columns: 1fr;
            gap: 1.25rem;
            margin-bottom: 1.5rem;
        }
        .chart-card {
            padding: 1rem;
            border-radius: 12px;
        }
        .tables-grid {
            grid-template-columns: 1fr;
            gap: 1.25rem;
        }
        .table-card {
            padding: 1rem;
            border-radius: 12px;
            overflow-x: auto;
        }
    }
</style>

<div class="dashboard-header">
    <h1 class="dashboard-title">Overview Dashboard</h1>
    <div>
        <span class="badge" style="background:var(--primary-light); color:var(--primary); font-weight:600; font-size:0.85rem; padding:0.4rem 1rem; border-radius:999px;">
            <ion-icon name="time-outline" style="vertical-align:middle; margin-right:0.25rem;"></ion-icon>
            As of {{ date('M d, Y H:i') }}
        </span>
    </div>
</div>

<!-- TOP FILTER BAR -->
<form method="GET" action="/dashboard" class="filter-bar" id="filterForm">
    <div class="filter-group">
        <label class="filter-label">Date Range</label>
        <select name="date_range" id="date_range" class="filter-select" onchange="toggleCustomDates(); document.getElementById('filterForm').submit();">
            <option value="today" {{ $range === 'today' ? 'selected' : '' }}>Today</option>
            <option value="this_week" {{ $range === 'this_week' ? 'selected' : '' }}>This Week</option>
            <option value="this_month" {{ $range === 'this_month' ? 'selected' : '' }}>This Month</option>
            <option value="this_quarter" {{ $range === 'this_quarter' ? 'selected' : '' }}>This Quarter</option>
            <option value="this_year" {{ $range === 'this_year' ? 'selected' : '' }}>This Year</option>
            <option value="custom" {{ $range === 'custom' ? 'selected' : '' }}>Custom</option>
        </select>
    </div>
    
    <div class="filter-group" id="custom_start_col" style="{{ $range === 'custom' ? '' : 'display:none;' }}">
        <label class="filter-label">Start Date</label>
        <input type="date" name="start_date" class="filter-input" value="{{ $startDate }}">
    </div>
    
    <div class="filter-group" id="custom_end_col" style="{{ $range === 'custom' ? '' : 'display:none;' }}">
        <label class="filter-label">End Date</label>
        <input type="date" name="end_date" class="filter-input" value="{{ $endDate }}">
    </div>

    <div class="filter-group">
        <label class="filter-label">Company</label>
        <select name="company_id" id="company_id" class="filter-select" onchange="filterDepartments(); document.getElementById('filterForm').submit();">
            <option value="">All Companies</option>
            @foreach($companies as $c)
                <option value="{{ $c->id }}" {{ $companyId == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
            @endforeach
        </select>
    </div>

    <div class="filter-group">
        <label class="filter-label">Department</label>
        <x-department-selector name="department_id" id="department_id" :departments="$departments" :selected="$deptId" onchange="document.getElementById('filterForm').submit();" />
    </div>

    @if($range === 'custom')
        <button type="submit" class="btn btn-primary-gradient" style="margin-top: 1.1rem; padding: 0.5rem 1.25rem; font-size: 0.85rem;">Apply Range</button>
    @endif
</form>

<!-- KPI TILES ROW -->
<div class="kpi-grid">
    <!-- Total Cash -->
    <div class="kpi-card kpi-primary" onclick="window.location='/master/bank-accounts'">
        <div class="kpi-header">
            <span class="kpi-title">Total Cash Position</span>
            <ion-icon name="wallet-outline" class="kpi-icon"></ion-icon>
        </div>
        <div class="kpi-value">${{ number_format($totalCash, 2) }}</div>
        <div class="kpi-meta">All accounts rollup</div>
    </div>

    <!-- Income -->
    <div class="kpi-card kpi-success" onclick="window.location='/transactions'">
        <div class="kpi-header">
            <span class="kpi-title">Total Income (Period)</span>
            <ion-icon name="trending-up-outline" class="kpi-icon" style="color:var(--success);"></ion-icon>
        </div>
        <div class="kpi-value">${{ number_format($totalIncome, 2) }}</div>
        <div class="kpi-meta">Includes paid invoices</div>
    </div>

    <!-- Expense -->
    <div class="kpi-card kpi-danger" onclick="window.location='/transactions'">
        <div class="kpi-header">
            <span class="kpi-title">Total Expense (Period)</span>
            <ion-icon name="trending-down-outline" class="kpi-icon" style="color:var(--danger);"></ion-icon>
        </div>
        <div class="kpi-value">${{ number_format($totalExpense, 2) }}</div>
        <div class="kpi-meta">Outflows recorded</div>
    </div>

    <!-- Profit / Loss -->
    <div class="kpi-card {{ $netProfit >= 0 ? 'kpi-success' : 'kpi-danger' }}" onclick="window.location='/transactions'">
        <div class="kpi-header">
            <span class="kpi-title">Net Profit/Loss</span>
            <ion-icon name="analytics-outline" class="kpi-icon"></ion-icon>
        </div>
        <div class="kpi-value">${{ number_format($netProfit, 2) }}</div>
        <div class="kpi-meta" style="{{ $netProfit >= 0 ? 'color:var(--success);' : 'color:var(--danger);' }}">
            {{ $netProfit >= 0 ? 'Surplus' : 'Deficit' }}
        </div>
    </div>

    <!-- Receivables -->
    <div class="kpi-card kpi-warning" onclick="window.location='/invoices'">
        <div class="kpi-header">
            <span class="kpi-title">Outstanding Receivables</span>
            <ion-icon name="document-text-outline" class="kpi-icon"></ion-icon>
        </div>
        <div class="kpi-value">${{ number_format($outstandingReceivables, 2) }}</div>
        <div class="kpi-meta">Unpaid invoice balances</div>
    </div>

    <!-- Payables -->
    <div class="kpi-card kpi-danger" onclick="window.location='/reports'">
        <div class="kpi-header">
            <span class="kpi-title">Outstanding Payables</span>
            <ion-icon name="calendar-outline" class="kpi-icon"></ion-icon>
        </div>
        <div class="kpi-value">${{ number_format($outstandingPayables, 2) }}</div>
        <div class="kpi-meta">Commissions + Interest due</div>
    </div>

    <!-- Budget Utilization -->
    <div class="kpi-card {{ $overBudgetCount > 0 ? 'kpi-danger' : 'kpi-warning' }}" onclick="window.location='/budgets'">
        <div class="kpi-header">
            <span class="kpi-title">Budget Utilization</span>
            <ion-icon name="pie-chart-outline" class="kpi-icon"></ion-icon>
        </div>
        <div class="kpi-value">{{ number_format($budgetUtilization, 1) }}%</div>
        <div class="kpi-meta">
            @if($overBudgetCount > 0)
                <span class="badge" style="background:var(--danger); color:#fff; font-size:0.7rem; padding:0.15rem 0.4rem; border-radius:4px;">{{ $overBudgetCount }} Over Budget</span>
            @else
                Across active budgets
            @endif
        </div>
    </div>

    <!-- Loans Outstanding -->
    <div class="kpi-card kpi-primary" onclick="window.location='/loans'">
        <div class="kpi-header">
            <span class="kpi-title">Active Loans Principal</span>
            <ion-icon name="card-outline" class="kpi-icon"></ion-icon>
        </div>
        <div class="kpi-value">${{ number_format($loansOutstanding, 2) }}</div>
        <div class="kpi-meta">Principal outstanding</div>
    </div>

    <!-- Pending Approvals -->
    <div class="kpi-card {{ $pendingApprovals > 0 ? 'kpi-danger' : 'kpi-primary' }}" onclick="window.location='/projects'">
        <div class="kpi-header">
            <span class="kpi-title">Pending Approvals</span>
            <ion-icon name="checkbox-outline" class="kpi-icon"></ion-icon>
        </div>
        <div class="kpi-value">{{ $pendingApprovals }}</div>
        <div class="kpi-meta">Awaiting Confirmation</div>
    </div>

    <!-- Reminders Due -->
    <div class="kpi-card {{ $remindersCount > 0 ? 'kpi-danger' : 'kpi-primary' }}" onclick="window.location='/master?tab=reminders'">
        <div class="kpi-header">
            <span class="kpi-title">Reminders Due</span>
            <ion-icon name="alert-circle-outline" class="kpi-icon"></ion-icon>
        </div>
        <div class="kpi-value">{{ $remindersCount }}</div>
        <div class="kpi-meta">Due today or overdue</div>
    </div>
</div>

<!-- CHARTS SECTION -->
<div class="charts-grid">
    <!-- Trend Chart -->
    <div class="chart-card">
        <div class="chart-header">
            <h3 class="chart-title">Income vs Expense Trend</h3>
        </div>
        <div style="position: relative; height: 300px; width: 100%;">
            <canvas id="trendChart"></canvas>
        </div>
    </div>

    <!-- Expense by Category Pie -->
    <div class="chart-card">
        <div class="chart-header">
            <h3 class="chart-title">Expense Breakdown by Category</h3>
        </div>
        <div style="position: relative; height: 300px; width: 100%;">
            <canvas id="categoryChart"></canvas>
        </div>
    </div>

    <!-- Receivables Aging -->
    <div class="chart-card">
        <div class="chart-header">
            <h3 class="chart-title">Receivables Aging</h3>
        </div>
        <div style="position: relative; height: 300px; width: 100%;">
            <canvas id="agingChart"></canvas>
        </div>
    </div>
</div>

<!-- TABLES SECTION -->
<div class="tables-grid">
    <!-- Upcoming & Overdue Payments -->
    <div class="table-card">
        <div class="table-header">
            <h3 class="table-title">
                <ion-icon name="time-outline" style="color:var(--warning);"></ion-icon>
                Upcoming & Overdue Receivables
            </h3>
            <a href="/invoices" class="table-action-link">View Invoices</a>
        </div>
        @if(empty($overduePayments))
            <p class="text-muted" style="text-align:center; padding:1.5rem 0;">No pending receivables found.</p>
        @else
            <table class="data-table" style="font-size:0.85rem;">
                <thead>
                    <tr>
                        <th>Ref</th>
                        <th>Client</th>
                        <th>Project</th>
                        <th>Due Date</th>
                        <th style="text-align:right;">Outstanding</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($overduePayments as $op)
                    <tr>
                        <td>{{ $op->ref }}</td>
                        <td>{{ $op->client }}</td>
                        <td>{{ $op->project }}</td>
                        <td>{{ $op->due_date }}</td>
                        <td style="text-align:right; font-weight:600;">${{ number_format($op->amount, 2) }}</td>
                        <td>
                            <span class="badge" style="
                                @if($op->status === 'Overdue') background:#fee2e2; color:#b91c1c;
                                @else background:#fef3c7; color:#d97706;
                                @endif
                            ">
                                {{ $op->status }}
                            </span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <!-- Active Reminders -->
    <div class="table-card">
        <div class="table-header">
            <h3 class="table-title">
                <ion-icon name="notifications-outline" style="color:var(--primary);"></ion-icon>
                Pending Reminders
            </h3>
            <a href="/master" class="table-action-link">Master Data</a>
        </div>
        @if($remindersList->isEmpty())
            <p class="text-muted" style="text-align:center; padding:1.5rem 0;">No pending reminders.</p>
        @else
            <table class="data-table" style="font-size:0.85rem;">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Due Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($remindersList as $rem)
                    <tr>
                        <td><span class="badge" style="background:#f1f5f9; color:#475569;">{{ ucfirst($rem->type) }}</span></td>
                        <td>{{ $rem->due_date }}</td>
                        <td>
                            <span class="badge" style="
                                @if($rem->due_date <= date('Y-m-d')) background:#fee2e2; color:#b91c1c;
                                @else background:#e0f2fe; color:#0369a1;
                                @endif
                            ">
                                {{ $rem->due_date <= date('Y-m-d') ? 'Overdue' : 'Pending' }}
                            </span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <!-- Today's Payment Milestones -->
    @if(isset($todayMilestones) && $todayMilestones->isNotEmpty())
    <div class="table-card" style="grid-column: span 2;">
        <div class="table-header">
            <h3 class="table-title">
                <ion-icon name="flag-outline" style="color: var(--danger);"></ion-icon>
                Today's Payment Milestones
            </h3>
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Milestone Name</th>
                    <th>Project</th>
                    <th>Amount</th>
                    <th style="text-align:right;">Action</th>
                </tr>
            </thead>
            <tbody>
                @foreach($todayMilestones as $milestone)
                <tr style="background: rgba(254, 226, 226, 0.2);">
                    <td style="font-weight:500;">{{ $milestone->name }}</td>
                    <td>{{ $milestone->project_name }}</td>
                    <td style="font-weight:600;"><x-amount-display :amount="$milestone->amount" currency="$" /></td>
                    <td style="text-align:right;">
                        <a href="/projects/{{ $milestone->project_id }}#payment-milestones" class="btn btn-sm btn-outline" style="color:var(--primary); border-color:var(--primary);">
                            Go to Project
                        </a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <!-- Recent Transactions -->
    <div class="table-card" style="grid-column: span 2;">
        <div class="table-header">
            <h3 class="table-title">
                <ion-icon name="list-outline" style="color:var(--success);"></ion-icon>
                Recent Transactions
            </h3>
            <a href="/transactions" class="table-action-link">All Transactions</a>
        </div>
        @if($recentTransactions->isEmpty())
            <p class="text-muted" style="text-align:center; padding:1.5rem 0;">No transactions recorded.</p>
        @else
            <table class="data-table" style="font-size:0.85rem;">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Category</th>
                        <th>Bank Account</th>
                        <th style="text-align:right;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($recentTransactions as $tx)
                    <tr>
                        <td>{{ $tx->transaction_date }}</td>
                        <td>
                            <span class="badge" style="
                                @if($tx->type === 'income') background:#dcfce7; color:#166534;
                                @else background:#fee2e2; color:#991b1b;
                                @endif
                            ">
                                {{ ucfirst($tx->type) }}
                            </span>
                        </td>
                        <td>{{ $tx->category_name }}</td>
                        <td>{{ $tx->bank_name }}</td>
                        <td style="text-align:right; font-weight:600; {{ $tx->type === 'income' ? 'color:var(--success);' : 'color:var(--danger);' }}">
                            {{ $tx->type === 'income' ? '+' : '-' }}${{ number_format($tx->amount, 2) }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>

@section('scripts')
<script>
    function toggleCustomDates() {
        const range = document.getElementById('date_range').value;
        const startCol = document.getElementById('custom_start_col');
        const endCol = document.getElementById('custom_end_col');
        if (range === 'custom') {
            startCol.style.display = 'block';
            endCol.style.display = 'block';
        } else {
            startCol.style.display = 'none';
            endCol.style.display = 'none';
        }
    }

    function filterDepartments() {
        const companyId = document.getElementById('company_id').value;
        const deptSelect = document.getElementById('department_id');
        const options = deptSelect.querySelectorAll('option');
        
        options.forEach(opt => {
            if (!opt.value) return;
            const comp = opt.getAttribute('data-company');
            if (!companyId || comp === companyId) {
                opt.style.display = '';
            } else {
                opt.style.display = 'none';
            }
        });
        
        // Reset if selected option is hidden
        const selectedOpt = deptSelect.options[deptSelect.selectedIndex];
        if (selectedOpt && selectedOpt.style.display === 'none') {
            deptSelect.value = '';
        }
    }

    // Initialize charts
    document.addEventListener('DOMContentLoaded', () => {
        filterDepartments();

        // 1. Trend Chart
        const trendCtx = document.getElementById('trendChart').getContext('2d');
        new Chart(trendCtx, {
            type: 'bar',
            data: {
                labels: {!! json_encode($trendData['labels']) !!},
                datasets: [
                    {
                        label: 'Income',
                        data: {!! json_encode($trendData['income']) !!},
                        backgroundColor: '#10b981',
                        borderRadius: 4
                    },
                    {
                        label: 'Expense',
                        data: {!! json_encode($trendData['expense']) !!},
                        backgroundColor: '#ef4444',
                        borderRadius: 4
                    },
                    {
                        label: 'Net Profit/Loss',
                        data: {!! json_encode($trendData['profit']) !!},
                        type: 'line',
                        borderColor: '#8b5cf6',
                        borderWidth: 2,
                        fill: false
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' }
                },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });

        // 2. Category Pie Chart
        const catCtx = document.getElementById('categoryChart').getContext('2d');
        new Chart(catCtx, {
            type: 'doughnut',
            data: {
                labels: {!! json_encode($expenseCategories->pluck('name')) !!},
                datasets: [{
                    data: {!! json_encode($expenseCategories->pluck('value')) !!},
                    backgroundColor: ['#8b5cf6', '#ef4444', '#f59e0b', '#10b981', '#6366f1']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });

        // 3. Aging Chart
        const agingCtx = document.getElementById('agingChart').getContext('2d');
        new Chart(agingCtx, {
            type: 'bar',
            data: {
                labels: ['0-30 Days', '31-60 Days', '61-90 Days', '90+ Days'],
                datasets: [{
                    label: 'Outstanding Balance ($)',
                    data: [
                        {{ $aging['30'] }},
                        {{ $aging['60'] }},
                        {{ $aging['90'] }},
                        {{ $aging['90_plus'] }}
                    ],
                    backgroundColor: '#f59e0b',
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    });
</script>
@endsection

@endsection
