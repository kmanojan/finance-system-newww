<aside class="sidebar-secondary" id="sidebarSecondary">
    <h2 class="sidebar-title">Master Data</h2>
    <nav class="nav-links">
        <a href="/master/departments" class="nav-link {{ request()->is('master/departments*') ? 'active' : '' }}">Departments</a>
        <a href="/master/categories" class="nav-link {{ request()->is('master/categories*') ? 'active' : '' }}">Categories</a>
        <a href="/master/invoice-types" class="nav-link {{ request()->is('master/invoice-types*') ? 'active' : '' }}">Invoice Types</a>
        <a href="/master/parties" class="nav-link {{ request()->is('master/parties*') ? 'active' : '' }}">Parties</a>
        <a href="/master/tags" class="nav-link {{ request()->is('master/tags*') ? 'active' : '' }}">Tags</a>
        <a href="/master/bank-accounts" class="nav-link {{ request()->is('master/bank-accounts*') ? 'active' : '' }}">Bank Accounts</a>
        <a href="/master/document-templates" class="nav-link {{ request()->is('master/document-templates*') ? 'active' : '' }}">Document Templates</a>
        <a href="/master/servers" class="nav-link {{ request()->is('master/servers*') ? 'active' : '' }}">Servers</a>
        <a href="/master/currencies" class="nav-link {{ request()->is('master/currencies*') ? 'active' : '' }}">Currencies</a>
        <a href="/master/tax-types" class="nav-link {{ request()->is('master/tax-types*') ? 'active' : '' }}">Tax Config & Rates</a>
        <a href="/master/users" class="nav-link {{ request()->is('master/users*') ? 'active' : '' }}">Users</a>
    </nav>
</aside>

