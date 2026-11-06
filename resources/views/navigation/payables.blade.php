<x-secondary-sidebar title="Payables & AP">
    <nav class="nav-links">
        <a href="/invoices" class="nav-link"><ion-icon name="document-text-outline" aria-hidden="true"></ion-icon><span class="section-navigation-label">Client Invoices (AR)</span></a>
        <a href="/payables/vendor-bills" class="nav-link {{ request()->is('payables/vendor-bills*') ? 'active' : '' }}"><ion-icon name="receipt-outline" aria-hidden="true"></ion-icon><span class="section-navigation-label">Vendor Bills (AP)</span></a>
        <a href="/payables/purchase-orders" class="nav-link {{ request()->is('payables/purchase-orders*') ? 'active' : '' }}"><ion-icon name="cart-outline" aria-hidden="true"></ion-icon><span class="section-navigation-label">Purchase Orders</span></a>
    </nav>
</x-secondary-sidebar>
