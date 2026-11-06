<x-secondary-sidebar title="Operations">
    <nav class="nav-links">
        <a href="/reminders" class="nav-link {{ request()->is('reminders*') ? 'active' : '' }}"><ion-icon name="notifications-outline" aria-hidden="true"></ion-icon><span class="section-navigation-label">Reminders &amp; Alerts @if(!empty($hasActiveReminders))<span class="notification-dot" title="Active reminders available" style="width:8px;height:8px;background:var(--danger);border-radius:50%;display:inline-block;"></span>@endif</span></a>
        <a href="/approvals" class="nav-link {{ request()->is('approvals*') ? 'active' : '' }}"><ion-icon name="checkbox-outline" aria-hidden="true"></ion-icon><span class="section-navigation-label">Approval Inbox</span></a>
        <a href="/cheques" class="nav-link {{ request()->is('cheques*') ? 'active' : '' }}"><ion-icon name="receipt-outline" aria-hidden="true"></ion-icon><span class="section-navigation-label">Cheques Tracking</span></a>
        <a href="/treasury/bank-reconciliation" class="nav-link {{ request()->is('treasury*') ? 'active' : '' }}"><ion-icon name="card-outline" aria-hidden="true"></ion-icon><span class="section-navigation-label">Bank Reconciliation</span></a>
        <a href="/assets/fixed-assets" class="nav-link {{ request()->is('assets*') ? 'active' : '' }}"><ion-icon name="desktop-outline" aria-hidden="true"></ion-icon><span class="section-navigation-label">Fixed Assets</span></a>
        <a href="/activity-logs" class="nav-link {{ request()->is('activity-logs*') ? 'active' : '' }}"><ion-icon name="list-outline" aria-hidden="true"></ion-icon><span class="section-navigation-label">Audit &amp; User Logs</span></a>
    </nav>
</x-secondary-sidebar>
