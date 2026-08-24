@props([
    'icon' => 'folder-open-outline',
    'title' => 'No records found',
    'description' => 'There are currently no entries matching your search or filters.',
    'actionUrl' => null,
    'actionText' => 'Create New',
    'actionModal' => null,
    'actionIcon' => 'add-circle-outline',
])

<div class="empty-state-card">
    <div class="empty-state-icon">
        <ion-icon name="{{ $icon }}"></ion-icon>
    </div>
    <div class="empty-state-title">{{ $title }}</div>
    @if($description)
        <p class="empty-state-desc">{{ $description }}</p>
    @endif

    @if($actionModal)
        <button type="button" class="btn btn-primary btn-pill" onclick="openModal('{{ $actionModal }}')">
            <ion-icon name="{{ $actionIcon }}"></ion-icon> {{ $actionText }}
        </button>
    @elseif($actionUrl)
        <a href="{{ $actionUrl }}" class="btn btn-primary btn-pill">
            <ion-icon name="{{ $actionIcon }}"></ion-icon> {{ $actionText }}
        </a>
    @endif
</div>
