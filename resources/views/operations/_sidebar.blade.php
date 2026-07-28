<aside class="sidebar-secondary" id="sidebarSecondary">
    <h2 class="sidebar-title">Operations</h2>
    <nav class="nav-links">
        <a href="/reminders" class="nav-link {{ request()->is('reminders*') ? 'active' : '' }}">
            <ion-icon name="notifications-outline"></ion-icon> Reminders & Alerts
        </a>
        <a href="/approvals" class="nav-link {{ request()->is('approvals*') ? 'active' : '' }}">
            <ion-icon name="checkbox-outline"></ion-icon> Approval Inbox
        </a>
        <a href="/cheques" class="nav-link {{ request()->is('cheques*') ? 'active' : '' }}">
            <ion-icon name="receipt-outline"></ion-icon> Cheques Tracking
        </a>
        <a href="/treasury/bank-reconciliation" class="nav-link {{ request()->is('treasury*') ? 'active' : '' }}">
            <ion-icon name="vault-outline"></ion-icon> Bank Reconciliation
        </a>
        <a href="/assets/fixed-assets" class="nav-link {{ request()->is('assets*') ? 'active' : '' }}">
            <ion-icon name="desktop-outline"></ion-icon> Fixed Assets
        </a>
        <a href="/activity-logs" class="nav-link {{ request()->is('activity-logs*') ? 'active' : '' }}">
            <ion-icon name="list-outline"></ion-icon> Audit & User Logs
        </a>
    </nav>
</aside>

