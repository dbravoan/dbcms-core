@props([
    'name' => 'market_ids',
    'selected' => [],
    'markets' => null,
    'emptyMeansAll' => true,
    'layout' => 'list',
    'help' => null,
    'group' => null,
])

@php
    /** @var \Illuminate\Support\Collection<int, \DbCms\Core\Models\Market> $allMarkets */
    $allMarkets = $markets ?? $pickerMarkets;

    $selectedIds = [];
    if ($selected instanceof \Illuminate\Support\Collection) {
        $selectedIds = $selected->map(static fn ($value): string => (string) $value)->all();
    } elseif (is_array($selected)) {
        $selectedIds = array_map('strval', array_values($selected));
    } elseif (is_string($selected) && trim($selected) !== '') {
        $selectedIds = array_map('trim', explode(',', $selected));
    }

    $emptyMeansAll = (bool) $emptyMeansAll;
    if ($emptyMeansAll && $selectedIds === []) {
        $selectedIds = $allMarkets->pluck('id')->map(static fn ($id): string => (string) $id)->all();
    }

    $marketLanguages = [];
    foreach ($allMarkets as $market) {
        $marketLanguages[(string) $market->id] = $pickerMarketLocales[(string) $market->id] ?? [];
    }

    $inputName = rtrim((string) $name, '[]');
@endphp

{{-- Reusable market assignment checkboxes. Emits `cms-markets-changed` so language tabs can filter. --}}
<div x-data="{
        selected: @js(array_values($selectedIds)),
        group: @js($group),
        marketLanguages: @js($marketLanguages),

        init() {
            this.emitChange();
        },

        get activeLocales() {
            const locales = new Set();
            this.selected.forEach((id) => (this.marketLanguages[id] || []).forEach((code) => locales.add(code)));
            return Array.from(locales);
        },

        hydrate(ids) {
            this.selected = (Array.isArray(ids) ? ids : []).map((id) => String(id));
            this.emitChange();
        },

        emitChange() {
            const payload = {
                group: this.group,
                selectedMarketIds: this.selected,
                activeLocales: this.selected.length === 0 ? null : this.activeLocales,
            };

            const globalContext = window.cmsContext = window.cmsContext || { locale: null, marketIds: [] };
            globalContext.marketIds = this.selected.map((id) => String(id));
            globalContext.activeLocales = payload.activeLocales;

            this.$dispatch('market-picker-change', payload);
            window.dispatchEvent(new CustomEvent('cms-markets-changed', { detail: payload }));
        }
     }"
     class="space-y-1.5">
    <div class="{{ $layout === 'grid' ? 'grid grid-cols-2 sm:grid-cols-3 gap-1.5' : 'space-y-1.5' }}">
        @forelse($allMarkets as $market)
            <label class="flex items-center gap-2 p-2 bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] rounded hover:border-[color:var(--admin-border)] cursor-pointer">
                <input type="checkbox"
                       name="{{ $inputName }}[]"
                       value="{{ $market->id }}"
                       x-model="selected"
                       @change="emitChange()"
                       class="rounded bg-[color:var(--admin-surface)] border-[color:var(--admin-border)] text-[color:var(--admin-accent)] focus:ring-[color:var(--admin-accent)]">
                <span class="text-xs text-[color:var(--admin-text)]">
                    {{ $market->name }}
                    <span class="text-[color:var(--admin-accent)] font-mono">[{{ strtoupper((string) $market->code) }}]</span>
                </span>
            </label>
        @empty
            <p class="text-[11px] text-[color:var(--admin-muted)]">{{ __('admin.market.empty') }}</p>
        @endforelse
    </div>

    @if($help)
        <p class="text-[11px] text-[color:var(--admin-muted)]">{{ $help }}</p>
    @endif
</div>
