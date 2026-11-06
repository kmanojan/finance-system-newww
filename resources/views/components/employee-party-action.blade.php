@props(['employee', 'compact' => false])
<div data-employee-party-action="{{ $employee->id }}" style="{{ $compact ? 'margin-top:8px;' : '' }}">
    @if($employee->party_id)
        <a href="/master/parties?party_id={{ $employee->party_id }}" class="btn btn-outline" style="white-space:nowrap;{{ $compact ? 'padding:0.35rem 0.75rem;font-size:0.8rem;' : '' }}" aria-label="View party for {{ $employee->full_name }}">View party</a>
    @else
        <form method="POST" action="{{ route('employees.sync_party', $employee->id) }}" style="margin:0;">
            @csrf
            <button type="submit" class="btn btn-outline" style="white-space:nowrap;{{ $compact ? 'padding:0.35rem 0.75rem;font-size:0.8rem;' : '' }}" aria-label="Sync {{ $employee->full_name }} as party">Sync as party</button>
        </form>
    @endif
</div>
