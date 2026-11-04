@props([
    'key' => '',
    'field' => [],
    'settings' => [],
])

@php
    /**
     * Single setting field shared by the global list and the language/market
     * tabs, so both render exactly the same control per type.
     */
    $type = $field['type'] ?? 'text';
    $label = $field['label'] ?? ucfirst(str_replace('_', ' ', $key));
    $desc = $field['description'] ?? null;
    $placeholder = $field['placeholder'] ?? '';
    $required = (bool) ($field['required'] ?? false);
    $secret = (bool) ($field['secret'] ?? false) || $type === 'password';
    $val = $settings[$key] ?? ($field['default'] ?? null);
    $hasSavedVal = ! empty($settings[$key]);
    $multiVal = is_array($val)
        ? $val
        : (is_string($val) && trim($val) !== '' ? array_map('trim', explode(',', $val)) : []);
@endphp

<div class="pt-6 first:pt-0 space-y-2">
    <!-- Label Row -->
    <div class="flex items-center justify-between">
        <label for="field-{{ $key }}" class="admin-field-label font-bold text-[color:var(--admin-text)] text-xs flex items-center gap-2">
            <span>{{ $label }}</span>
            @if($required)
                <span class="text-[9px] bg-red-500/10 text-red-400 border border-red-500/30 px-1.5 py-0.2 rounded font-bold uppercase">Requerido</span>
            @endif
            @if($secret)
                <span class="text-[9px] bg-[color:var(--admin-accent)]/10 text-[color:var(--admin-accent)] border border-[color:var(--admin-accent)]/30 px-1.5 py-0.2 rounded font-bold uppercase">Cifrado AES-256</span>
            @endif
        </label>

        <span class="text-[10px] text-[color:var(--admin-muted)] font-mono">key: {{ $key }}</span>
    </div>

    <!-- Field Descriptions -->
    @if($desc)
        <p class="text-[11px] text-[color:var(--admin-muted)] leading-relaxed">{{ $desc }}</p>
    @endif

    <!-- Input Renderers by Type -->
    @if($type === 'boolean')
        <div class="pt-1">
            <label class="inline-flex items-center gap-3 cursor-pointer">
                <input type="checkbox"
                       name="{{ $key }}"
                       id="field-{{ $key }}"
                       value="1"
                       {{ $val ? 'checked' : '' }}
                       class="w-4 h-4 rounded bg-[color:var(--admin-surface)] border-[color:var(--admin-border)] text-[color:var(--admin-accent)] focus:ring-0 focus:ring-offset-0 focus:border-[color:var(--admin-accent)] cursor-pointer">
                <span class="text-xs text-[color:var(--admin-text)] select-none">Habilitar esta opción</span>
            </label>
        </div>

    @elseif($type === 'select')
        <select name="{{ $key }}"
                id="field-{{ $key }}"
                class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] hover:border-[color:var(--admin-border)] focus:border-[color:var(--admin-accent)] rounded-lg p-2.5 text-[color:var(--admin-text)] font-mono text-xs transition-colors focus:outline-none">
            @foreach(($field['options'] ?? []) as $optKey => $optLabel)
                <option value="{{ $optKey }}" {{ (string)$val === (string)$optKey ? 'selected' : '' }}>
                    {{ $optLabel }}
                </option>
            @endforeach
        </select>

    @elseif($type === 'roles_multiselect')
        <x-admin.role-multiselect
            :name="$key"
            :selected="$val"
            :placeholder="$placeholder ?: 'Seleccionar roles...'"
        />

    @elseif($type === 'entities_multiselect')
        <x-admin.entity-multiselect
            :name="$key"
            :selected="$val"
            :placeholder="$placeholder ?: 'Seleccionar entidades...'"
        />

    @elseif($type === 'multiselect')
        <x-admin.multiselect
            :name="$key"
            :options="$field['options'] ?? []"
            :selected="$val"
            :placeholder="$placeholder ?: 'Seleccionar opciones...'"
        />

    @elseif($type === 'market_select')
        <x-admin.market-select
            :name="$key"
            :value="$val"
            :placeholder="$placeholder ?: 'Seleccionar mercado...'"
        />

    @elseif($type === 'market_multiselect')
        <x-admin.market-select
            :name="$key"
            :values="$multiVal"
            multiple
            :placeholder="$placeholder ?: 'Seleccionar mercados...'"
        />

    @elseif($type === 'language_select')
        <x-admin.language-select
            :name="$key"
            :value="$val"
            :placeholder="$placeholder ?: 'Seleccionar idioma...'"
        />

    @elseif($type === 'language_multiselect')
        <x-admin.language-select
            :name="$key"
            :values="$multiVal"
            multiple
            :placeholder="$placeholder ?: 'Seleccionar idiomas...'"
        />

    @elseif($type === 'entity_select')
        <x-admin.search-select
            :source="$field['source'] ?? 'pages'"
            :name="$key"
            :value="$val"
            :placeholder="$placeholder ?: 'Buscar...'"
        />

    @elseif($type === 'entity_multiselect')
        <x-admin.search-select
            :source="$field['source'] ?? 'pages'"
            :name="$key"
            :values="$multiVal"
            multiple
            :placeholder="$placeholder ?: 'Buscar...'"
        />

    @elseif($type === 'textarea')
        <textarea name="{{ $key }}"
                  id="field-{{ $key }}"
                  rows="4"
                  placeholder="{{ $placeholder }}"
                  class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] hover:border-[color:var(--admin-border)] focus:border-[color:var(--admin-accent)] rounded-lg p-3 text-[color:var(--admin-text)] font-mono text-xs transition-colors focus:outline-none placeholder-[color:var(--admin-muted)] leading-relaxed">{{ $val }}</textarea>

    @elseif($secret)
        <!-- Password / Secret Input with Visibility Toggle -->
        <div x-data="{ show: false }" class="space-y-1">
            <div class="relative flex items-center">
                <input :type="show ? 'text' : 'password'"
                       name="{{ $key }}"
                       id="field-{{ $key }}"
                       placeholder="{{ $hasSavedVal ? '•••••••••••••••• (Clave guardada - Deja vacío para mantener)' : $placeholder }}"
                       autocomplete="new-password"
                       class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] hover:border-[color:var(--admin-border)] focus:border-[color:var(--admin-accent)] rounded-lg px-3 py-2 pr-20 text-[color:var(--admin-text)] font-mono text-xs transition-colors focus:outline-none placeholder-[color:var(--admin-muted)]">

                <button type="button"
                        @click="show = !show"
                        class="absolute right-2 px-2.5 py-1 bg-[color:var(--admin-surface-raised)] hover:bg-[color:var(--admin-surface-raised)] border border-[color:var(--admin-border)] rounded text-[10px] text-[color:var(--admin-text)] hover:text-[color:var(--admin-text)] transition-colors">
                    <span class="inline-flex items-center gap-1">
                        <i :class="show ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye'"></i>
                        <span x-text="show ? 'Ocultar' : 'Revelar'"></span>
                    </span>
                </button>
            </div>

            @if($hasSavedVal)
                <p class="text-[10px] text-[color:var(--admin-accent)] flex items-center gap-1.5 pt-0.5">
                    <i class="fa-solid fa-check"></i>
                    <span>Clave almacenada de forma segura y cifrada. Si dejas el campo en blanco, se conservará el valor existente.</span>
                </p>
            @endif
        </div>

    @elseif($type === 'datetime-local')
        <input type="datetime-local"
               name="{{ $key }}"
               id="field-{{ $key }}"
               value="{{ $val }}"
               class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] hover:border-[color:var(--admin-border)] focus:border-[color:var(--admin-accent)] rounded-lg px-3 py-2.5 text-[color:var(--admin-text)] font-mono text-xs transition-colors focus:outline-none">

    @elseif($type === 'color')
        <div class="flex items-center gap-3">
            <input type="color"
                   name="{{ $key }}"
                   id="field-{{ $key }}"
                   value="{{ $val ?: '#00FF99' }}"
                   oninput="document.getElementById('label-{{ $key }}').textContent = this.value"
                   class="w-10 h-10 rounded border border-[color:var(--admin-border)] bg-[color:var(--admin-surface)] cursor-pointer p-0.5">
            <span class="text-xs text-[color:var(--admin-text)] font-mono" id="label-{{ $key }}">{{ $val ?: '#00FF99' }}</span>
        </div>

    @else
        <!-- Standard Single-Line Input -->
        <input type="{{ $type === 'number' ? 'number' : ($type === 'email' ? 'email' : ($type === 'url' ? 'url' : 'text')) }}"
               name="{{ $key }}"
               id="field-{{ $key }}"
               value="{{ $val }}"
               placeholder="{{ $placeholder }}"
               class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] hover:border-[color:var(--admin-border)] focus:border-[color:var(--admin-accent)] rounded-lg px-3 py-2.5 text-[color:var(--admin-text)] font-mono text-xs transition-colors focus:outline-none placeholder-[color:var(--admin-muted)]">
    @endif

    @error($key)
        <p class="text-xs text-red-400 mt-1">{{ $message }}</p>
    @enderror
</div>
