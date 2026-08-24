@if ($paginator->hasPages())
    <nav class="custom-pagination" style="display:flex; align-items:center; gap:0.35rem; flex-wrap:wrap;" aria-label="Pagination Navigation">
        {{-- Previous Page Link --}}
        @if ($paginator->onFirstPage())
            <span class="page-link disabled" style="opacity:0.4; cursor:not-allowed; padding:0.4rem 0.75rem; border-radius:6px; border:1px solid var(--border); background:var(--bg-card); color:var(--text-muted); font-size:0.85rem; display:inline-flex; align-items:center; gap:0.25rem;">
                <ion-icon name="chevron-back-outline"></ion-icon> Prev
            </span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" class="page-link" style="padding:0.4rem 0.75rem; border-radius:6px; border:1px solid var(--border); background:var(--bg-card); color:var(--text-main); font-size:0.85rem; text-decoration:none; display:inline-flex; align-items:center; gap:0.25rem; transition:all 0.2s;">
                <ion-icon name="chevron-back-outline"></ion-icon> Prev
            </a>
        @endif

        {{-- Pagination Elements --}}
        @foreach ($elements as $element)
            {{-- "Three Dots" Separator --}}
            @if (is_string($element))
                <span class="page-link disabled" style="padding:0.4rem 0.6rem; color:var(--text-muted); font-size:0.85rem;">{{ $element }}</span>
            @endif

            {{-- Array Of Links --}}
            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span class="page-link active" style="padding:0.4rem 0.75rem; border-radius:6px; background:var(--primary); border:1px solid var(--primary); color:#ffffff; font-weight:700; font-size:0.85rem;">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}" class="page-link" style="padding:0.4rem 0.75rem; border-radius:6px; border:1px solid var(--border); background:var(--bg-card); color:var(--text-main); font-size:0.85rem; text-decoration:none; transition:all 0.2s;">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach

        {{-- Next Page Link --}}
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" class="page-link" style="padding:0.4rem 0.75rem; border-radius:6px; border:1px solid var(--border); background:var(--bg-card); color:var(--text-main); font-size:0.85rem; text-decoration:none; display:inline-flex; align-items:center; gap:0.25rem; transition:all 0.2s;">
                Next <ion-icon name="chevron-forward-outline"></ion-icon>
            </a>
        @else
            <span class="page-link disabled" style="opacity:0.4; cursor:not-allowed; padding:0.4rem 0.75rem; border-radius:6px; border:1px solid var(--border); background:var(--bg-card); color:var(--text-muted); font-size:0.85rem; display:inline-flex; align-items:center; gap:0.25rem;">
                Next <ion-icon name="chevron-forward-outline"></ion-icon>
            </span>
        @endif
    </nav>
@endif
