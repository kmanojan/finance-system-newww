<x-secondary-sidebar title="Loan Management">
    <nav class="nav-links">
        <a href="/loans" class="nav-link {{ (request()->is('loans') || is_numeric(request()->segment(2))) ? 'active' : '' }}"><ion-icon aria-hidden="true" name="cash-outline"></ion-icon><span class="section-navigation-label">
             Active Loans</span></a>
        <a href="/loans/schedules" class="nav-link {{ request()->is('loans/schedules') ? 'active' : '' }}"><ion-icon aria-hidden="true" name="calendar-outline"></ion-icon><span class="section-navigation-label">
             Schedules</span></a>
        <a href="/loans/settlements" class="nav-link {{ request()->is('loans/settlements') ? 'active' : '' }}"><ion-icon aria-hidden="true" name="checkmark-done-circle-outline"></ion-icon><span class="section-navigation-label">
             Settlements</span></a>
        <a href="/loans/party-report" class="nav-link {{ request()->is('loans/party-report') ? 'active' : '' }}"><ion-icon aria-hidden="true" name="pie-chart-outline"></ion-icon><span class="section-navigation-label">
             Party balances</span></a>
    </nav>
</x-secondary-sidebar>
