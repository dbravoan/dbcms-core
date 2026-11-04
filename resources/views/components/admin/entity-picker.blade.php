@props([
    'items' => [],
    'placeholder' => 'Buscar página, artículo o categoría...',
    'onSelect' => 'onSelectEntity($item)',
])

<div x-data="{
        open: false,
        search: '',
        items: {{ json_encode($items) }},
        get filtered() {
            if (!this.search.trim()) return this.items.slice(0, 30);
            const q = this.search.toLowerCase().trim();
            return this.items.filter(i =>
                (i.label && i.label.toLowerCase().includes(q)) ||
                (i.url && i.url.toLowerCase().includes(q)) ||
                (i.type && i.type.toLowerCase().includes(q))
            ).slice(0, 30);
        },
        select(item) {
            {{ $onSelect }};
            this.open = false;
            this.search = '';
        }
     }"
     class="relative font-mono text-xs"
     @click.outside="open = false"
     @keydown.escape.window="open = false">

    <div class="relative">
        <input type="text"
               x-model="search"
               @focus="open = true"
               @input="open = true"
               placeholder="{{ $placeholder }}"
               class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] hover:border-[color:var(--admin-accent)]/60 focus:border-[color:var(--admin-accent)] rounded-lg pl-8 pr-3 py-2 text-[color:var(--admin-text)] text-xs placeholder-[color:var(--admin-muted)] transition-all">
        <i class="fa-solid fa-magnifying-glass absolute left-2.5 top-3 text-[color:var(--admin-muted)] text-xs"></i>
    </div>

    <div x-show="open"
         x-cloak
         x-transition
         class="absolute z-50 left-0 right-0 mt-1 max-h-60 overflow-y-auto rounded-xl bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] shadow-2xl divide-y divide-[color:var(--admin-border)]/80">

        <template x-for="item in filtered" :key="item.type + '-' + (item.id || item.url)">
            <div @click="select(item)"
                 class="p-2.5 hover:bg-[color:var(--admin-surface-raised)] cursor-pointer flex items-center justify-between gap-3 transition-colors group">
                <div class="flex items-center gap-2.5 min-w-0">
                    <span class="w-6 h-6 rounded bg-[color:var(--admin-surface)]/50 border border-[color:var(--admin-border)] flex items-center justify-center text-xs text-[color:var(--admin-accent)] shrink-0">
                        <i :class="item.icon || 'fa-solid fa-link'"></i>
                    </span>
                    <div class="truncate">
                        <span class="font-bold text-[color:var(--admin-text)] text-xs group-hover:text-[color:var(--admin-accent)] transition-colors truncate block" x-text="item.label"></span>
                        <span class="text-[10px] text-[color:var(--admin-muted)] font-mono truncate block" x-text="item.url"></span>
                    </div>
                </div>
                <span class="text-[9px] px-1.5 py-0.5 rounded bg-[color:var(--admin-surface)]/60 border border-[color:var(--admin-border)] text-[color:var(--admin-muted)] font-mono uppercase shrink-0" x-text="item.badge"></span>
            </div>
        </template>

        <div x-show="filtered.length === 0" class="p-4 text-center text-[color:var(--admin-muted)] text-xs">
            No se encontraron contenidos para "<span x-text="search"></span>"
        </div>
    </div>
</div>
