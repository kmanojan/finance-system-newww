<x-secondary-sidebar title="Employees & payroll">
    <nav class="nav-links" aria-label="Employees and payroll">
        <a href="/employees" class="nav-link {{ request()->is('employees*') ? 'active' : '' }}"><ion-icon name="people-outline" aria-hidden="true"></ion-icon><span class="section-navigation-label">Employees</span></a>
        <a href="/payroll" class="nav-link {{ request()->is('payroll*') && !request()->is('payroll/advances*') ? 'active' : '' }}"><ion-icon name="calendar-outline" aria-hidden="true"></ion-icon><span class="section-navigation-label">Monthly payroll</span></a>
        <a href="/payroll/advances" class="nav-link {{ request()->is('payroll/advances*') ? 'active' : '' }}"><ion-icon name="wallet-outline" aria-hidden="true"></ion-icon><span class="section-navigation-label">Salary advances</span></a>
        <a href="/master/salary-components" class="nav-link"><ion-icon name="options-outline" aria-hidden="true"></ion-icon><span class="section-navigation-label">Salary components</span></a>
    </nav>
</x-secondary-sidebar>
