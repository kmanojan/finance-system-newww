<x-secondary-sidebar title="Ledger">
    <nav class="nav-links">
        <a href="/transactions" class="nav-link {{ request()->is('transactions') ? 'active' : '' }}"><ion-icon aria-hidden="true" name="list-outline"></ion-icon><span class="section-navigation-label">
             All Transactions</span></a>
        <a href="/journal-entries" class="nav-link {{ request()->is('journal-entries') ? 'active' : '' }}"><ion-icon aria-hidden="true" name="book-outline"></ion-icon><span class="section-navigation-label">
             Journal Entries</span></a>
        <a href="/budgets" class="nav-link {{ request()->is('budgets*') ? 'active' : '' }}"><ion-icon aria-hidden="true" name="calculator-outline"></ion-icon><span class="section-navigation-label">
             Budgets</span></a>
    </nav>
</x-secondary-sidebar>
