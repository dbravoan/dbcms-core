@props([
    'languages' => [],
    'active' => null,
    'flags' => [],
    'group' => null,
    'activeLocales' => null,
])

@php
    $langs = $languages instanceof \Illuminate\Support\Collection ? $languages : collect($languages);
    $activeCode = $active ?? ($langs->first()->code ?? 'es');
    $initialVisible = is_array($activeLocales) && $activeLocales !== [] ? $activeLocales : null;

    $flags = (is_array($flags) && $flags !== []) ? $flags : ($languageFlags ?? []);
@endphp

{{-- Reusable multilingual tab bar. Filters itself when markets change (same behaviour as Pages). --}}
<div x-data="{
        activeLocale: @js($activeCode),
        languages: @js($langs->pluck('code')->values()),
        group: @js($group),
        visibleLocales: @js($initialVisible),

        init() {
            window.addEventListener('cms-markets-changed', (event) => this.receiveMarketsChanged(event.detail || {}));
            window.addEventListener('cms-locale-switched', (event) => {
                if (this.languages.includes(event.detail?.locale)) this.activeLocale = event.detail.locale;
            });
            const globalContext = window.cmsContext = window.cmsContext || { locale: null, marketIds: [] };
            globalContext.locale = this.activeLocale;
        },

        receiveMarketsChanged(detail) {
            if (this.group !== null && detail.group !== this.group) {
                return;
            }

            this.visibleLocales = (detail.activeLocales && detail.activeLocales.length) ? detail.activeLocales : null;
            this.ensureValidLocale();
        },

        isVisible(code) {
            return this.visibleLocales === null || this.visibleLocales.includes(code);
        },

        ensureValidLocale() {
            if (this.isVisible(this.activeLocale)) {
                return;
            }
            const first = this.languages.find((code) => this.isVisible(code));
            if (first) {
                this.switchTo(first);
            }
        },

        switchTo(code) {
            this.activeLocale = code;
            const globalContext = window.cmsContext = window.cmsContext || { locale: null, marketIds: [] };
            globalContext.locale = code;
            this.$dispatch('locale-tab-change', { locale: code });
            window.dispatchEvent(new CustomEvent('cms-locale-switched', { detail: { locale: code } }));
        }
     }"
     class="flex flex-wrap items-center gap-2">
    @forelse($langs as $lang)
        <button type="button"
                x-show="isVisible('{{ $lang->code }}')"
                @click="switchTo('{{ $lang->code }}')"
                :class="activeLocale === '{{ $lang->code }}' ? 'bg-[color:var(--admin-accent)] text-black font-bold' : 'text-[color:var(--admin-muted)] hover:text-[color:var(--admin-text)]'"
                class="px-3.5 py-1.5 rounded transition-all flex items-center gap-1.5">
            <span>{{ $flags[$lang->code] ?? '🌐' }}</span>
            <span>{{ $lang->name }} ({{ strtoupper($lang->code) }})</span>
        </button>
    @empty
        <span class="text-[11px] text-[color:var(--admin-muted)]">{{ __('admin.language.none') }}</span>
    @endforelse
    <button type="button" x-data="{ available: !!window.CmsEntityTranslator }" x-cloak
            x-show="available && $el.closest('form')?.hasAttribute('data-cms-translation-editor')"
            @click="window.CmsEntityTranslator.open($el.closest('form'))"
            class="px-3.5 py-1.5 rounded border border-[color:var(--admin-accent)]/30 text-[color:var(--admin-accent)] hover:bg-[color:var(--admin-accent)]/10 flex items-center gap-1.5">
        <i class="fa-solid fa-language" aria-hidden="true"></i>{{ __('ai.translation.title') }}
    </button>
</div>
