<x-secondary-sidebar title="Master Data">
    <nav class="nav-links">
        <a href="/master/departments" class="nav-link {{ request()->is('master/departments*') ? 'active' : '' }}"><ion-icon name="business-outline" aria-hidden="true"></ion-icon><span class="section-navigation-label">Departments</span></a>
        <a href="/master/categories" class="nav-link {{ request()->is('master/categories*') ? 'active' : '' }}"><ion-icon name="albums-outline" aria-hidden="true"></ion-icon><span class="section-navigation-label">Categories</span></a>
        <a href="/master/invoice-types" class="nav-link {{ request()->is('master/invoice-types*') ? 'active' : '' }}"><ion-icon name="documents-outline" aria-hidden="true"></ion-icon><span class="section-navigation-label">Invoice Types</span></a>
        <a href="/master/parties" class="nav-link {{ request()->is('master/parties*') ? 'active' : '' }}"><ion-icon name="people-outline" aria-hidden="true"></ion-icon><span class="section-navigation-label">Parties</span></a>
        <a href="/master/tags" class="nav-link {{ request()->is('master/tags*') ? 'active' : '' }}"><ion-icon name="pricetags-outline" aria-hidden="true"></ion-icon><span class="section-navigation-label">Tags</span></a>
        <a href="/master/bank-accounts" class="nav-link {{ request()->is('master/bank-accounts*') ? 'active' : '' }}"><ion-icon name="card-outline" aria-hidden="true"></ion-icon><span class="section-navigation-label">Bank Accounts</span></a>
        <a href="/master/document-templates" class="nav-link {{ request()->is('master/document-templates*') ? 'active' : '' }}"><ion-icon name="document-text-outline" aria-hidden="true"></ion-icon><span class="section-navigation-label">Document Templates</span></a>
        <a href="/master/servers" class="nav-link {{ request()->is('master/servers*') ? 'active' : '' }}"><ion-icon name="server-outline" aria-hidden="true"></ion-icon><span class="section-navigation-label">Servers</span></a>
        <a href="/master/currencies" class="nav-link {{ request()->is('master/currencies*') ? 'active' : '' }}"><ion-icon name="cash-outline" aria-hidden="true"></ion-icon><span class="section-navigation-label">Currencies</span></a>
        <a href="/master/tax-types" class="nav-link {{ request()->is('master/tax-types*') ? 'active' : '' }}"><ion-icon name="calculator-outline" aria-hidden="true"></ion-icon><span class="section-navigation-label">Tax Config & Rates</span></a>
        <a href="/master/salary-components" class="nav-link {{ request()->is('master/salary-components*') ? 'active' : '' }}"><ion-icon name="wallet-outline" aria-hidden="true"></ion-icon><span class="section-navigation-label">Salary Components</span></a>
        <a href="/master/accounts" class="nav-link {{ request()->is('master/accounts*') ? 'active' : '' }}"><ion-icon name="book-outline" aria-hidden="true"></ion-icon><span class="section-navigation-label">Chart of accounts</span></a>
        @if(auth()->user()?->isAdmin())
        <a href="/settings/financial-years" class="nav-link {{ request()->is('settings/financial-years*') ? 'active' : '' }}"><ion-icon name="calendar-outline" aria-hidden="true"></ion-icon><span class="section-navigation-label">Financial years</span></a>
        <a href="/master/users" class="nav-link {{ request()->is('master/users*') ? 'active' : '' }}"><ion-icon name="person-circle-outline" aria-hidden="true"></ion-icon><span class="section-navigation-label">Users</span></a>
        @endif
    </nav>
</x-secondary-sidebar>

