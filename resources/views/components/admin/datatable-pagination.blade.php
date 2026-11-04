@props([
    'paginator' => null,
])

@if($paginator && $paginator->total() > 0)
    <div class="admin-pagination px-6 py-4 bg-[color:var(--admin-surface)] border-t border-[color:var(--admin-border)] font-mono flex flex-col sm:flex-row items-center justify-between gap-4 text-xs">
        <div class="text-[color:var(--admin-muted)]">
            {{ __('admin.table.showing', [
                'from' => $paginator->firstItem() ?? 0,
                'to' => $paginator->lastItem() ?? 0,
                'total' => $paginator->total(),
            ]) }}
        </div>

        <div class="flex items-center space-x-1">
            {{-- Previous Page Link --}}
            @if($paginator->onFirstPage())
                <span class="px-3 py-1.5 rounded bg-[color:var(--admin-surface-raised)]/40 text-[color:var(--admin-muted)] cursor-not-allowed">&laquo;</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="admin-pagination-item px-3 py-1.5 rounded bg-[color:var(--admin-surface)] hover:bg-[color:var(--admin-surface-raised)] border border-[color:var(--admin-border)] text-[color:var(--admin-text)] hover:text-[color:var(--admin-text)] transition-colors">&laquo;</a>
            @endif

            {{-- Pagination Elements --}}
            @foreach($paginator->getUrlRange(max(1, $paginator->currentPage() - 2), min($paginator->lastPage(), $paginator->currentPage() + 2)) as $page => $url)
                @if($page == $paginator->currentPage())
                    <span class="admin-pagination-active px-3 py-1.5 rounded bg-[color:var(--admin-accent)]/10 border border-[color:var(--admin-accent)] text-[color:var(--admin-accent)] font-bold">{{ $page }}</span>
                @else
                    <a href="{{ $url }}" class="admin-pagination-item px-3 py-1.5 rounded bg-[color:var(--admin-surface)] hover:bg-[color:var(--admin-surface-raised)] border border-[color:var(--admin-border)] text-[color:var(--admin-text)] hover:text-[color:var(--admin-text)] transition-colors">{{ $page }}</a>
                @endif
            @endforeach

            {{-- Next Page Link --}}
            @if($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="admin-pagination-item px-3 py-1.5 rounded bg-[color:var(--admin-surface)] hover:bg-[color:var(--admin-surface-raised)] border border-[color:var(--admin-border)] text-[color:var(--admin-text)] hover:text-[color:var(--admin-text)] transition-colors">&raquo;</a>
            @else
                <span class="px-3 py-1.5 rounded bg-[color:var(--admin-surface-raised)]/40 text-[color:var(--admin-muted)] cursor-not-allowed">&raquo;</span>
            @endif
        </div>
    </div>
@endif
