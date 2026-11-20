@props([
    'source',
    'name' => null,
    'field' => null,
    'value' => null,
    'values' => [],
    'initial' => [],
    'multiple' => false,
    'valueKey' => null,
    'placeholder' => null,
    'perPage' => 20,
    'disabled' => false,
    'contextual' => false,
    'help' => null,
])

@php
    $placeholder = $placeholder ?? __('admin.search.placeholder');
    $isMultiple = (bool) $multiple;

    $selected = [];
    if (is_array($initial) && $initial !== []) {
        foreach ($initial as $item) {
            if (is_array($item) && array_key_exists('value', $item)) {
                $selected[] = ['value' => (string) $item['value'], 'label' => (string) ($item['label'] ?? $item['value'])];
            } elseif (is_scalar($item)) {
                $selected[] = ['value' => (string) $item, 'label' => (string) $item];
            }
        }
    } else {
        $rawValues = $isMultiple ? (array) $values : ($value !== null && $value !== '' ? [$value] : []);
        foreach ($rawValues as $raw) {
            if (is_scalar($raw)) {
                $selected[] = ['value' => (string) $raw, 'label' => (string) $raw];
            }
        }
    }

    $hasName = is_string($name) && $name !== '';
    $inputName = $hasName ? rtrim($name, '[]') : null;

    $config = [
        'source' => $source,
        'name' => $inputName,
        'field' => $field,
        'multiple' => $isMultiple,
        'valueKey' => $valueKey,
        'perPage' => (int) $perPage,
        'placeholder' => $placeholder,
        'contextual' => (bool) $contextual,
        'url' => route('admin.api.entity-search'),
        'selected' => $selected,
    ];
@endphp

@once
    @push('admin_scripts')
        <script nonce="{{ $cspNonce ?? '' }}">
            window.searchSelect = function (config) {
                return {
                    config: config,
                    open: false,
                    search: '',
                    loading: false,
                    error: '',
                    results: [],
                    selected: (config.selected || []).map(function (item) {
                        return { value: String(item.value), label: String(item.label), icon: item.icon || 'fa-solid fa-tag' };
                    }),
                    activeIndex: -1,
                    _timer: null,
                    _controller: null,
                    context: { locale: null, marketIds: [] },

                    init() {
                        this.refreshContext();

                        window.addEventListener('cms-locale-switched', () => {
                            this.refreshContext();
                            if (this.open) {
                                this.load();
                            }
                        });

                        window.addEventListener('cms-markets-changed', () => {
                            this.refreshContext();
                            if (this.open) {
                                this.load();
                            }
                        });

                        this.$watch('open', (value) => {
                            if (! value) {
                                this.search = '';
                                this.activeIndex = -1;
                            }
                        });

                        this.resolveLabels();
                    },

                    refreshContext() {
                        const global = window.cmsContext = window.cmsContext || { locale: null, marketIds: [] };
                        this.context = {
                            locale: global.locale || null,
                            marketIds: Array.isArray(global.marketIds) ? global.marketIds : [],
                        };
                    },

                    applyContext(url) {
                        if (! this.config.contextual) {
                            return url;
                        }

                        if (this.context.locale) {
                            url.searchParams.set('locale', this.context.locale);
                        }

                        (this.context.marketIds || []).forEach((id, index) => {
                            url.searchParams.append('market_ids[' + index + ']', String(id));
                        });

                        return url;
                    },

                    get isMultiple() {
                        return !! this.config.multiple;
                    },

                    async resolveLabels() {
                        const hasConfigName = this.config.name || this.config.labelEndpoint !== false;
                        if (! hasConfigName || this.selected.length === 0) {
                            return;
                        }

                        // Values supplied without a human label are resolved remotely.
                        const pending = this.selected.filter((item) => ! item.label || item.label === item.value);
                        if (pending.length === 0) {
                            return;
                        }

                        try {
                            const url = new URL(this.config.url, window.location.origin);
                            url.searchParams.set('source', this.config.source);
                            if (this.config.valueKey) {
                                url.searchParams.set('value_key', this.config.valueKey);
                            }
                            this.applyContext(url);
                            pending.forEach((item, index) => url.searchParams.append('ids[' + index + ']', item.value));

                            const response = await fetch(url.toString(), { headers: { Accept: 'application/json' } });
                            if (! response.ok) {
                                return;
                            }

                            const data = await response.json();
                            const byValue = new Map((data.results || []).map((row) => [String(row.value), row]));

                            this.selected = this.selected.map((item) => {
                                const resolved = byValue.get(String(item.value));

                                return resolved
                                    ? { value: String(resolved.value), label: resolved.label, icon: resolved.icon }
                                    : item;
                            });
                        } catch (error) {
                            // Keep the raw values if resolution fails.
                        }
                    },

                    get placeholderText() {
                        return this.selected.length ? '' : this.config.placeholder;
                    },

                    get hasSelection() {
                        return this.selected.length > 0;
                    },

                    openDropdown() {
                        if (this.open) {
                            return;
                        }
                        this.open = true;
                        if (this.results.length === 0) {
                            this.load();
                        }
                        this.$nextTick(() => this.$refs.input && this.$refs.input.focus());
                    },

                    toggleOpen() {
                        if (this.open) {
                            this.close();
                        } else {
                            this.openDropdown();
                        }
                    },

                    close() {
                        this.open = false;
                        this.search = '';
                        this.activeIndex = -1;
                    },

                    onInput() {
                        clearTimeout(this._timer);
                        this._timer = setTimeout(() => this.load(), 250);
                    },

                    async load() {
                        if (this._controller) {
                            this._controller.abort();
                        }
                        this._controller = new AbortController();
                        this.loading = true;
                        this.error = '';

                        try {
                            const url = new URL(this.config.url, window.location.origin);
                            url.searchParams.set('source', this.config.source);
                            url.searchParams.set('per_page', String(this.config.perPage || 20));
                            if (this.search.trim() !== '') {
                                url.searchParams.set('q', this.search.trim());
                            }
                            if (this.config.valueKey) {
                                url.searchParams.set('value_key', this.config.valueKey);
                            }
                            this.applyContext(url);

                            const response = await fetch(url.toString(), {
                                headers: { Accept: 'application/json' },
                                signal: this._controller.signal,
                            });

                            if (! response.ok) {
                                throw new Error('HTTP ' + response.status);
                            }

                            const data = await response.json();
                            this.results = Array.isArray(data.results) ? data.results : [];
                            this.activeIndex = this.results.length ? 0 : -1;
                        } catch (error) {
                            if (error.name !== 'AbortError') {
                                this.error = @js(__('admin.search.error'));
                                this.results = [];
                            }
                        } finally {
                            this.loading = false;
                        }
                    },

                    isSelected(value) {
                        return this.selected.some((item) => String(item.value) === String(value));
                    },

                    select(item) {
                        if (this.isMultiple) {
                            if (this.isSelected(item.value)) {
                                this.remove(item.value);
                            } else {
                                this.selected.push({ value: String(item.value), label: item.label, icon: item.icon });
                            }
                            this.search = '';
                            this.activeIndex = this.results.length ? 0 : -1;
                            if (this.$refs.input) {
                                this.$refs.input.focus();
                            }
                        } else {
                            this.selected = [{ value: String(item.value), label: item.label, icon: item.icon }];
                            this.close();
                        }
                        this.emitChange();
                    },

                    remove(value) {
                        this.selected = this.selected.filter((item) => String(item.value) !== String(value));
                        this.emitChange();
                    },

                    hydrate(value) {
                        const raw = this.isMultiple
                            ? (Array.isArray(value) ? value : (value ? [value] : []))
                            : (value ? [value] : []);

                        const existing = new Map(this.selected.map((item) => [String(item.value), item]));

                        this.selected = raw.map((item) => existing.get(String(item)) || {
                            value: String(item),
                            label: String(item),
                            icon: 'fa-solid fa-tag',
                        });

                        this.resolveLabels();
                    },

                    clear() {
                        this.selected = [];
                        this.emitChange();
                    },

                    move(delta) {
                        if (this.results.length === 0) {
                            return;
                        }
                        this.activeIndex = (this.activeIndex + delta + this.results.length) % this.results.length;
                    },

                    onEnter() {
                        if (this.activeIndex >= 0 && this.results[this.activeIndex]) {
                            this.select(this.results[this.activeIndex]);
                        }
                    },

                    emitChange() {
                        this.$dispatch('search-select-change', {
                            name: this.config.name,
                            field: this.config.field,
                            value: this.isMultiple ? this.selected.map((item) => item.value) : (this.selected[0] ? this.selected[0].value : null),
                            selected: this.selected,
                        });
                    },
                };
            };
        </script>
    @endpush
@endonce

<div x-data="searchSelect(@js($config))"
     {{ $attributes }}
     x-on:click.outside="close()"
     x-on:keydown.escape.window="open && close()"
     class="relative font-mono text-xs {{ $disabled ? 'opacity-60 pointer-events-none' : '' }}">

    @if($inputName !== null)
        @if($isMultiple)
            <template x-for="item in selected" :key="item.value">
                <input type="hidden" name="{{ $inputName }}[]" :value="item.value">
            </template>
        @else
            <input type="hidden" name="{{ $inputName }}" :value="selected[0] ? selected[0].value : ''">
        @endif
    @endif

    <!-- Control -->
    <div @click="openDropdown()"
         class="admin-combobox cursor-text">

        <template x-for="item in selected" :key="item.value">
            <span class="admin-selection-chip">
                <i :class="item.icon || 'fa-solid fa-tag'" class="text-[10px] opacity-80"></i>
                <span x-text="item.label"></span>
                <button type="button" @click.stop="remove(item.value)" class="p-0.5 ml-1 text-[color:var(--admin-muted)] hover:text-red-400 transition-colors" title="{{ __('admin.search.remove') }}">
                    <i class="fa-solid fa-xmark text-[10px]"></i>
                </button>
            </span>
        </template>

        <input type="text"
               x-ref="input"
               x-model="search"
               @input="onInput()"
               @focus="openDropdown()"
               @keydown.arrow-down.prevent="move(1)"
               @keydown.arrow-up.prevent="move(-1)"
               @keydown.enter.prevent="onEnter()"
               :placeholder="placeholderText"
               class="admin-control--embedded flex-1 min-w-[120px]">

        <i x-show="loading" class="fa-solid fa-spinner fa-spin text-[color:var(--admin-muted)] text-xs" x-cloak></i>
        <button type="button" x-show="! loading" @click.stop="toggleOpen()" class="p-1 text-[color:var(--admin-muted)] hover:text-[color:var(--admin-text)] transition-transform duration-200" :class="open ? 'rotate-180' : ''">
            <i class="fa-solid fa-chevron-down text-xs"></i>
        </button>
    </div>

    <!-- Dropdown -->
    <div x-show="open"
         x-cloak
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 translate-y-1"
         x-transition:enter-end="opacity-100 translate-y-0"
         class="admin-dropdown absolute z-50 left-0 right-0 mt-1 max-h-64 overflow-y-auto font-mono text-xs">

        <div x-show="loading && results.length === 0" class="p-4 text-center text-[color:var(--admin-muted)]">
            <i class="fa-solid fa-spinner fa-spin mr-1"></i> {{ __('admin.common.searching') }}
        </div>

        <div x-show="! loading && error !== ''" class="p-4 text-center text-red-400" x-text="error"></div>

        <div x-show="! loading && error === '' && results.length === 0" class="p-4 text-center text-[color:var(--admin-muted)]">
            {{ __('admin.search.empty') }}
        </div>

        <template x-for="(item, idx) in results" :key="item.value">
            <div @click="select(item)"
                 @mouseenter="activeIndex = idx"
                 :class="activeIndex === idx ? 'admin-option--active' : ''"
                 class="admin-option p-3 cursor-pointer flex items-center justify-between gap-3 transition-colors group">
                <div class="flex items-center gap-3 min-w-0">
                    <span class="w-7 h-7 rounded-lg flex items-center justify-center text-xs shrink-0 bg-[color:var(--admin-surface)]/50 text-[color:var(--admin-muted)] border border-[color:var(--admin-border)] group-hover:text-[color:var(--admin-accent)]"
                          :class="isSelected(item.value) ? 'bg-[color:var(--admin-accent)]/20 text-[color:var(--admin-accent)] border-[color:var(--admin-accent)]/40' : ''">
                        <i :class="item.icon || 'fa-solid fa-tag'"></i>
                    </span>
                    <div class="min-w-0">
                        <span class="font-bold text-[color:var(--admin-text)] text-xs block truncate" x-text="item.label"></span>
                        <span class="text-[9px] uppercase text-[color:var(--admin-muted)]" x-text="item.badge"></span>
                    </div>
                </div>
                <i x-show="isSelected(item.value)" class="fa-solid fa-check text-[color:var(--admin-accent)] text-xs shrink-0"></i>
            </div>
        </template>
    </div>

    @if($help)
        <p class="text-[11px] text-[color:var(--admin-muted)] mt-1 font-mono">{{ $help }}</p>
    @endif
</div>
