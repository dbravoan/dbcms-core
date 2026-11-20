@props([
    'name',
    'options' => [],
    'selected' => [],
    'placeholder' => null,
    'label' => null,
    'asArray' => false,
    'help' => null,
])

@php
    $placeholder = $placeholder ?? __('admin.multiselect.placeholder');
    // Normalize selected into array of strings
    $initialSelected = [];
    if (is_array($selected)) {
        $initialSelected = array_map('strval', $selected);
    } elseif (is_string($selected) && trim($selected) !== '') {
        $initialSelected = array_map('trim', explode(',', $selected));
    }

    // Normalize options
    $normalizedOptions = [];
    foreach ($options as $key => $opt) {
        if (is_string($opt)) {
            $val = is_numeric($key) ? $opt : (string) $key;
            $normalizedOptions[] = [
                'value' => $val,
                'label' => $opt,
                'description' => '',
                'icon' => 'fa-solid fa-tag',
                'badge' => strtoupper($val),
            ];
        } elseif (is_array($opt)) {
            $val = (string) ($opt['value'] ?? $key);
            $normalizedOptions[] = [
                'value' => $val,
                'label' => (string) ($opt['label'] ?? $opt['name'] ?? $val),
                'description' => (string) ($opt['description'] ?? $opt['desc'] ?? ''),
                'icon' => (string) ($opt['icon'] ?? 'fa-solid fa-tag'),
                'badge' => (string) ($opt['badge'] ?? strtoupper($val)),
            ];
        }
    }

    $isInputArray = $asArray || str_ends_with($name, '[]');
    $cleanName = rtrim($name, '[]');
@endphp

<div x-data="{
        open: false,
        search: '',
        selectedValues: {{ json_encode($initialSelected) }},
        options: {{ json_encode($normalizedOptions) }},

        get filteredOptions() {
            if (!this.search.trim()) return this.options;
            const q = this.search.toLowerCase().trim();
            return this.options.filter(o =>
                o.label.toLowerCase().includes(q) ||
                o.value.toLowerCase().includes(q) ||
                (o.description && o.description.toLowerCase().includes(q))
            );
        },

        isSelected(val) {
            return this.selectedValues.includes(String(val));
        },

        toggle(val) {
            const strVal = String(val);
            if (this.isSelected(strVal)) {
                this.remove(strVal);
            } else {
                this.selectedValues.push(strVal);
            }
        },

        remove(val) {
            const strVal = String(val);
            this.selectedValues = this.selectedValues.filter(v => v !== strVal);
        },

        clearAll() {
            this.selectedValues = [];
        },

        getOption(val) {
            return this.options.find(o => o.value === String(val)) || {
                value: val,
                label: val,
                icon: 'fa-solid fa-circle',
                badge: val
            };
        },

        get commaSeparatedValue() {
            return this.selectedValues.join(',');
        }
     }"
     class="space-y-2 relative font-mono text-xs"
     @click.outside="open = false"
     @keydown.escape.window="open = false">

    @if($label)
        <div class="flex items-center justify-between">
            <label class="admin-field-label block text-[color:var(--admin-text)] font-bold uppercase text-[11px] tracking-wider">
                {{ $label }}
            </label>
            <button type="button"
                    x-show="selectedValues.length > 0"
                    @click="clearAll()"
                    class="text-[10px] text-[color:var(--admin-muted)] hover:text-red-400 transition-colors">
                <i class="fa-solid fa-trash-can mr-1"></i> {{ __('admin.multiselect.clear') }}
            </button>
        </div>
    @endif

    <!-- Hidden form inputs -->
    @if($isInputArray)
        <template x-for="val in selectedValues" :key="val">
            <input type="hidden" name="{{ $cleanName }}[]" :value="val">
        </template>
    @else
        <input type="hidden" name="{{ $cleanName }}" :value="commaSeparatedValue">
    @endif

    <!-- Input Box & Selected Chips Container -->
    <div @click="open = true; $nextTick(() => $refs.searchInput?.focus())"
         class="admin-combobox cursor-text">

        <!-- Selected Tags / Chips -->
        <template x-for="val in selectedValues" :key="val">
            <span class="admin-selection-chip">
                <i :class="getOption(val).icon" class="text-[10px] opacity-80"></i>
                <span x-text="getOption(val).label"></span>
                <button type="button"
                        @click.stop="remove(val)"
                        class="p-0.5 ml-1 text-[color:var(--admin-muted)] hover:text-red-400 rounded-full transition-colors"
                        title="{{ __('admin.multiselect.remove') }}">
                    <i class="fa-solid fa-xmark text-[10px]"></i>
                </button>
            </span>
        </template>

        <!-- Search Input -->
        <input type="text"
               x-ref="searchInput"
               x-model="search"
               @focus="open = true"
               @keydown.backspace="if (!search && selectedValues.length > 0) { remove(selectedValues[selectedValues.length - 1]); }"
               placeholder="{{ $placeholder }}"
               class="admin-control--embedded flex-1 min-w-[140px]">

        <!-- Indicator Arrow -->
        <button type="button"
                @click.stop="open = !open"
                class="p-1 text-[color:var(--admin-muted)] hover:text-[color:var(--admin-text)] rounded transition-transform duration-200"
                :class="open ? 'rotate-180' : ''">
            <i class="fa-solid fa-chevron-down text-xs"></i>
        </button>
    </div>

    <!-- Dropdown Options Panel -->
    <div x-show="open"
         x-cloak
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 translate-y-1"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-100"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 translate-y-1"
         class="admin-dropdown absolute z-50 left-0 right-0 mt-1 max-h-64 overflow-y-auto font-mono text-xs">

        <!-- Empty state -->
        <div x-show="filteredOptions.length === 0" class="p-4 text-center text-[color:var(--admin-muted)]">
            <i class="fa-solid fa-circle-question text-base mb-1 block opacity-40"></i>
            {{ __('admin.multiselect.no_match') }} "<span x-text="search" class="text-[color:var(--admin-text)]"></span>"
        </div>

        <!-- Options list -->
        <template x-for="option in filteredOptions" :key="option.value">
            <div @click="toggle(option.value)"
                 class="admin-option p-3 cursor-pointer flex items-center justify-between gap-3 transition-colors group"
                 :class="isSelected(option.value) ? 'bg-[color:var(--admin-surface-raised)]/90 border-l-2 border-l-[color:var(--admin-accent)]' : ''">

                <div class="flex items-start gap-3 flex-1">
                    <div class="w-7 h-7 rounded-lg flex items-center justify-center text-xs shrink-0 transition-colors"
                         :class="isSelected(option.value) ? 'bg-[color:var(--admin-accent)]/20 text-[color:var(--admin-accent)] border border-[color:var(--admin-accent)]/40' : 'bg-[color:var(--admin-surface)]/50 text-[color:var(--admin-muted)] border border-[color:var(--admin-border)] group-hover:text-[color:var(--admin-text)]'">
                        <i :class="option.icon"></i>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="font-bold text-[color:var(--admin-text)] text-xs" x-text="option.label"></span>
                            <span class="text-[9px] px-1.5 py-0.2 rounded bg-[color:var(--admin-surface)]/60 border border-[color:var(--admin-border)] text-[color:var(--admin-muted)] uppercase font-mono" x-text="option.badge || option.value"></span>
                        </div>
                        <p x-show="option.description" class="text-[11px] text-[color:var(--admin-muted)] mt-0.5 leading-snug" x-text="option.description"></p>
                    </div>
                </div>

                <!-- Checkbox status indicator -->
                <div class="w-5 h-5 rounded flex items-center justify-center border transition-colors shrink-0"
                     :class="isSelected(option.value) ? 'bg-[color:var(--admin-accent)] border-[color:var(--admin-accent)] text-[color:var(--admin-on-accent)] font-bold' : 'border-[color:var(--admin-border)] bg-[color:var(--admin-surface)] group-hover:border-[color:var(--admin-border)]'">
                    <i x-show="isSelected(option.value)" class="fa-solid fa-check text-[10px]"></i>
                </div>
            </div>
        </template>
    </div>

    @if($help)
        <p class="text-[11px] text-[color:var(--admin-muted)] mt-1 font-mono">{{ $help }}</p>
    @endif
</div>
