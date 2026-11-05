@props([
    'name' => 'flag',
    'value' => '',
    'placeholder' => null,
    // Bandera gobernada por un estado de Alpine (formulario de edición): se pasa
    // como expresión y el componente la sigue mientras el modal esté abierto.
    'bind' => null,
])

@php
    $globe = \DbCms\Core\Localization\FlagCatalog::GLOBE;
    $options = \DbCms\Core\Localization\FlagCatalog::options(app()->getLocale());
    $current = trim((string) $value) !== '' ? (string) $value : $globe;
    $inputPlaceholder = $placeholder ?? __('admin.markets.emoji_placeholder');
@endphp

{{-- Flag / emoji picker: a searchable grid (with the neutral globe) plus a free
     emoji input. Works for markets (country, group or international) and
     languages. Extra attributes (e.g. `x-init`) pass through to the root. --}}
<div
    x-data="{
        open: false,
        query: '',
        value: @js($current),
        options: @js($options),
        syncFrom(external) {
            if (typeof external === 'string' || typeof external === 'number') {
                this.value = String(external) || @js($globe);
            }
        },
        pick(emoji) { this.value = emoji; this.open = false; },
        get filtered() {
            const q = this.query.trim().toLowerCase();
            if (! q) return this.options;
            return this.options.filter((option) =>
                option.label.toLowerCase().includes(q) || (option.code || '').toLowerCase().includes(q)
            );
        },
    }"
    @if($bind !== null) x-init="$watch(() => { try { return {{ $bind }}; } catch (e) { return null; } }, (v) => syncFrom(v), { immediate: true })" @endif
    {{ $attributes->merge(['class' => 'relative']) }}
>
    <input type="hidden" name="{{ $name }}" :value="value">

    <div class="flex items-center gap-2">
        <button type="button"
                @click="open = !open"
                class="w-12 h-10 shrink-0 bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] hover:border-[color:var(--admin-accent)] rounded-lg text-lg leading-none flex items-center justify-center transition-colors"
                aria-label="{{ __('admin.markets.select_emoji') }}">
            <span x-text="value || '{{ $globe }}'"></span>
        </button>
        <input type="text"
               x-model="value"
               maxlength="16"
               placeholder="{{ $inputPlaceholder }}"
               class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] focus:border-[color:var(--admin-accent)] rounded-lg px-3 py-2 text-[color:var(--admin-text)] text-center text-lg">
    </div>

    <div x-show="open"
         style="display:none"
         @click.away="open = false"
         class="absolute z-50 mt-2 left-0 w-72 bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] rounded-xl shadow-2xl p-3 space-y-2">
        <input type="text"
               x-model="query"
               placeholder="{{ __('admin.markets.search_emoji') }}"
               class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] focus:border-[color:var(--admin-accent)] rounded-lg px-3 py-1.5 text-[color:var(--admin-text)] text-xs">

        <div class="grid grid-cols-7 gap-1 max-h-56 overflow-y-auto">
            <button type="button"
                    @click="pick('{{ $globe }}')"
                    title="{{ __('admin.markets.flag_international') }}"
                    class="h-8 rounded hover:bg-[color:var(--admin-surface-raised)] text-lg leading-none">🌐</button>
            <template x-for="option in filtered" :key="option.value + option.code">
                <button type="button"
                        @click="pick(option.value)"
                        :title="option.label + (option.code ? ' (' + option.code + ')' : '')"
                        class="h-8 rounded hover:bg-[color:var(--admin-surface-raised)] text-lg leading-none"
                        x-text="option.value"></button>
            </template>
        </div>

        <div class="flex items-center justify-between pt-2 border-t border-[color:var(--admin-border)]">
            <span class="text-[10px] text-[color:var(--admin-muted)]" x-text="filtered.length + ' emojis'"></span>
            <button type="button" @click="pick('')" class="text-[10px] text-[color:var(--admin-muted)] hover:text-[color:var(--admin-text)]">
                {{ __('admin.markets.clear_emoji') }}
            </button>
        </div>
    </div>
</div>
