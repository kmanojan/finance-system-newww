@props(['title'])
<aside class="sidebar-secondary" id="sidebarSecondary" aria-label="{{ $title }} navigation">
    <div class="section-navigation-header">
        <h2 class="sidebar-title">{{ $title }}</h2>
        <button type="button" class="navigation-toggle" data-toggle-secondary aria-controls="sidebarSecondary" aria-expanded="true" title="Close section navigation" aria-label="Close section navigation"><ion-icon name="chevron-back-outline" aria-hidden="true"></ion-icon></button>
    </div>
    <div class="section-navigation-content">{{ $slot }}</div>
</aside>
