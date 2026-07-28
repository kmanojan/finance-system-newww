@extends('reports.layout')
@section('report-title', 'Cost Allocation Report')

@section('report-content')
<header class="page-header" style="margin-bottom: 1.5rem;">
    <div class="header-titles">
        <h1 style="font-size:1.75rem; font-weight:800; color:var(--text-heading); margin:0;">Cost Allocation Report</h1>
        <p class="subtitle" style="margin-top:0.3rem;">Detailed breakdown of allocated employee and server costs.</p>
    </div>
</header>

<div style="margin-bottom: 1.5rem;">
    <x-date-range-picker :from="$from" :to="$to" />
</div>

<div>
    {{-- Filters --}}
    <div style="background: var(--bg-card); padding: 1.25rem; border-radius: 12px; border: 1px solid var(--border); margin-bottom: 1.5rem;">
        <form action="" method="GET" style="display: flex; flex-wrap: wrap; gap: 1rem; align-items: flex-end;">
            <input type="hidden" name="from" value="{{ $from }}">
            <input type="hidden" name="to" value="{{ $to }}">

            <div style="flex: 1; min-width: 140px;">
                <label class="form-label" style="font-size: 0.8rem; margin-bottom: 0.25rem;">Type</label>
                <select name="type" class="form-control" style="padding: 0.5rem; font-size: 0.85rem;">
                    <option value="">All Types</option>
                    <option value="employee" {{ $type === 'employee' ? 'selected' : '' }}>Employee</option>
                    <option value="server" {{ $type === 'server' ? 'selected' : '' }}>Server</option>
                    <option value="other" {{ $type === 'other' ? 'selected' : '' }}>Other</option>
                </select>
            </div>

            <div style="flex: 1.2; min-width: 180px;">
                <label class="form-label" style="font-size: 0.8rem; margin-bottom: 0.25rem;">Project</label>
                <select name="project_id" class="form-control" style="padding: 0.5rem; font-size: 0.85rem;">
                    <option value="">All Projects</option>
                    @foreach($projects as $p)
                        <option value="{{ $p->id }}" {{ $projectId == $p->id ? 'selected' : '' }}>{{ $p->name }}</option>
                    @endforeach
                </select>
            </div>

            <div style="flex: 1.2; min-width: 180px;">
                <label class="form-label" style="font-size: 0.8rem; margin-bottom: 0.25rem;">Employee</label>
                <select name="employee_id" class="form-control" style="padding: 0.5rem; font-size: 0.85rem;">
                    <option value="">All Employees</option>
                    @foreach($employees as $e)
                        <option value="{{ $e->id }}" {{ $employeeId == $e->id ? 'selected' : '' }}>{{ $e->full_name }}</option>
                    @endforeach
                </select>
            </div>

            <div style="display: flex; gap: 0.5rem; align-items: flex-end;">
                <button type="submit" class="btn btn-primary" style="padding: 0.5rem 1.25rem; font-size: 0.85rem;">Filter</button>
                <a href="{{ route('reports.cost_allocations') }}" class="btn btn-outline" style="padding: 0.5rem 1rem; font-size: 0.85rem; text-decoration: none;">Reset</a>
            </div>
        </form>
    </div>

    {{-- KPI Cards Grid --}}
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
        
        <div style="background: var(--bg-card); padding: 1.25rem; border-radius: 12px; border: 1px solid var(--border);">
            <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 500; margin-bottom: 0.5rem;">Total Cost Allocated</div>
            <div style="font-size: 1.5rem; font-weight: 700; color: var(--text-heading);">
                LKR {{ number_format($totalCost, 2) }}
            </div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.4rem;">
                {{ $allocations->count() }} total record(s)
            </div>
        </div>

        <div style="background: var(--bg-card); padding: 1.25rem; border-radius: 12px; border: 1px solid var(--border);">
            <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 500; margin-bottom: 0.5rem;">Employee Costs</div>
            <div style="font-size: 1.5rem; font-weight: 700; color: var(--primary);">
                LKR {{ number_format($employeeCost, 2) }}
            </div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.4rem;">
                {{ $totalCost > 0 ? round(($employeeCost / $totalCost) * 100, 1) : 0 }}% of total allocations
            </div>
        </div>

        <div style="background: var(--bg-card); padding: 1.25rem; border-radius: 12px; border: 1px solid var(--border);">
            <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 500; margin-bottom: 0.5rem;">Server / Hosting Costs</div>
            <div style="font-size: 1.5rem; font-weight: 700; color: #d97706;">
                LKR {{ number_format($serverCost, 2) }}
            </div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.4rem;">
                {{ $totalCost > 0 ? round(($serverCost / $totalCost) * 100, 1) : 0 }}% of total allocations
            </div>
        </div>

        <div style="background: var(--bg-card); padding: 1.25rem; border-radius: 12px; border: 1px solid var(--border);">
            <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 500; margin-bottom: 0.5rem;">Other Cost Centers</div>
            <div style="font-size: 1.5rem; font-weight: 700; color: #475569;">
                LKR {{ number_format($otherCost, 2) }}
            </div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.4rem;">
                {{ $totalCost > 0 ? round(($otherCost / $totalCost) * 100, 1) : 0 }}% of total allocations
            </div>
        </div>
    </div>

    {{-- Top Project Breakdown Summary --}}
    @if($projectBreakdown->count() > 0)
    <div style="background: var(--bg-card); padding: 1.25rem; border-radius: 12px; border: 1px solid var(--border); margin-bottom: 1.5rem;">
        <h3 style="font-size: 1rem; font-weight: 600; color: var(--text-heading); margin-bottom: 1rem;">Project Cost Summary Breakdown</h3>
        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
            @foreach($projectBreakdown as $pb)
            <div>
                <div style="display: flex; justify-content: space-between; font-size: 0.85rem; margin-bottom: 0.35rem;">
                    <span style="font-weight: 500; color: var(--text-heading);">{{ $pb['project_name'] }}</span>
                    <span style="font-weight: 600; color: var(--text-main);">LKR {{ number_format($pb['total'], 2) }} <small style="color:var(--text-muted); font-weight:normal;">({{ $pb['count'] }} entries)</small></span>
                </div>
                @php
                    $pct = $totalCost > 0 ? round(($pb['total'] / $totalCost) * 100, 1) : 0;
                @endphp
                <div style="height: 6px; background: var(--bg-page); border-radius: 3px; overflow: hidden;">
                    <div style="height: 100%; width: {{ $pct }}%; background: var(--primary); border-radius: 3px;"></div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Detailed Records Table --}}
    <div style="background: var(--bg-card); border-radius: 12px; border: 1px solid var(--border); overflow: hidden;">
        <div style="padding: 1rem 1.25rem; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
            <h3 style="font-size: 1rem; font-weight: 600; color: var(--text-heading); margin: 0;">Detailed Allocation Log</h3>
            <span style="font-size: 0.8rem; color: var(--text-muted);">From {{ date('M d, Y', strtotime($from)) }} to {{ date('M d, Y', strtotime($to)) }}</span>
        </div>

        <table class="data-table" style="width: 100%; border-collapse: collapse;">
            <thead style="background: var(--bg-page); border-bottom: 1px solid var(--border);">
                <tr>
                    <th style="padding: 0.85rem 1rem; text-align: left; font-size: 0.8rem; color: var(--text-muted);">Date</th>
                    <th style="padding: 0.85rem 1rem; text-align: left; font-size: 0.8rem; color: var(--text-muted);">Type</th>
                    <th style="padding: 0.85rem 1rem; text-align: left; font-size: 0.8rem; color: var(--text-muted);">Project</th>
                    <th style="padding: 0.85rem 1rem; text-align: left; font-size: 0.8rem; color: var(--text-muted);">Entity / Description</th>
                    <th style="padding: 0.85rem 1rem; text-align: left; font-size: 0.8rem; color: var(--text-muted);">Notes</th>
                    <th style="padding: 0.85rem 1rem; text-align: left; font-size: 0.8rem; color: var(--text-muted);">Source</th>
                    <th style="padding: 0.85rem 1rem; text-align: right; font-size: 0.8rem; color: var(--text-muted);">Amount</th>
                </tr>
            </thead>
            <tbody>
                @forelse($allocations as $item)
                <tr style="border-bottom: 1px solid var(--border-light);">
                    <td style="padding: 0.85rem 1rem; font-size: 0.85rem; color: var(--text-main);">
                        {{ date('M d, Y', strtotime($item->period_start)) }}
                    </td>
                    <td style="padding: 0.85rem 1rem;">
                        @if($item->type === 'employee')
                            <span style="background: #e0e7ff; color: #3730a3; padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.75rem; font-weight: 600;">Employee</span>
                        @elseif($item->type === 'server')
                            <span style="background: #fef3c7; color: #92400e; padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.75rem; font-weight: 600;">Server</span>
                        @else
                            <span style="background: #f1f5f9; color: #475569; padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.75rem; font-weight: 600;">Other</span>
                        @endif
                    </td>
                    <td style="padding: 0.85rem 1rem; font-size: 0.85rem; font-weight: 500; color: var(--text-heading);">
                        {{ $item->project->name ?? 'Unassigned' }}
                    </td>
                    <td style="padding: 0.85rem 1rem; font-size: 0.85rem;">
                        @if($item->type === 'employee')
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <img src="{{ $item->employee->profile_picture_url ?? 'https://ui-avatars.com/api/?name='.urlencode($item->employee->full_name ?? 'Emp') }}" style="width: 24px; height: 24px; border-radius: 50%; object-fit: cover;">
                                <div>
                                    <span style="font-weight: 500; color: var(--text-heading);">{{ $item->employee->full_name ?? 'Unknown' }}</span>
                                    <small style="color: var(--text-muted); display: block; font-size: 0.75rem;">{{ $item->employee->job_position ?? '' }}</small>
                                </div>
                            </div>
                        @elseif($item->type === 'server')
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <ion-icon name="server-outline" style="color: var(--primary); font-size: 1.1rem;"></ion-icon>
                                <div>
                                    <span style="font-weight: 500; color: var(--text-heading);">{{ $item->server->name ?? 'Unknown' }}</span>
                                    <small style="color: var(--text-muted); display: block; font-size: 0.75rem;">{{ $item->server->provider ?? '' }}</small>
                                </div>
                            </div>
                        @else
                            <span style="font-weight: 500; color: var(--text-heading);">{{ $item->cost_center_name ?? 'Cost Center' }}</span>
                        @endif
                    </td>
                    <td style="padding: 0.85rem 1rem; font-size: 0.8rem; color: var(--text-muted);">
                        {{ $item->notes ?? '-' }}
                    </td>
                    <td style="padding: 0.85rem 1rem;">
                        <span style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted);">
                            {{ $item->source ?? 'manual' }}
                        </span>
                    </td>
                    <td style="padding: 0.85rem 1rem; text-align: right; font-size: 0.85rem; font-weight: 600; color: var(--text-heading);">
                        {{ $item->currency }} {{ number_format($item->amount, 2) }}
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" style="text-align: center; padding: 3rem; color: var(--text-muted);">
                        <ion-icon name="calculator-outline" style="font-size: 3rem; opacity: 0.4; margin-bottom: 0.5rem;"></ion-icon><br>
                        No cost allocations found for the selected date range and filters.
                    </td>
                </tr>
                @endforelse
            </tbody>
            @if($allocations->count() > 0)
            <tfoot style="background: var(--bg-page); border-top: 2px solid var(--border); font-weight: 700;">
                <tr>
                    <td colspan="6" style="padding: 1rem; text-align: right; color: var(--text-heading);">Total Allocated Cost:</td>
                    <td style="padding: 1rem; text-align: right; color: var(--primary); font-size: 1rem;">
                        LKR {{ number_format($totalCost, 2) }}
                    </td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>
</div>
@endsection
