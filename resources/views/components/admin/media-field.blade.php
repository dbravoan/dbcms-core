@props([
    'name',
    'field',
    'label',
    'hint' => null,
])

{{-- Media picker bound to the surrounding Alpine `media[field]` state and the global filemanager modal. --}}
<div class="space-y-1.5">
    <label class="admin-field-label font-bold text-[color:var(--admin-text)] text-xs" for="{{ $name }}">{{ $label }}</label>

    <div class="flex items-start gap-3">
        <div class="w-16 h-16 rounded-lg bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] flex items-center justify-center overflow-hidden shrink-0">
            <template x-if="media['{{ $field }}']">
                <img :src="media['{{ $field }}']" alt="{{ $label }}" class="w-full h-full object-contain">
            </template>
            <template x-if="! media['{{ $field }}']">
                <i class="fa-solid fa-image text-[color:var(--admin-muted)] text-lg"></i>
            </template>
        </div>

        <div class="flex-1 min-w-0 space-y-2">
            <input type="text"
                   id="{{ $name }}"
                   name="{{ $name }}"
                   x-model="media['{{ $field }}']"
                   class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] focus:border-[color:var(--admin-accent)] rounded-lg px-3 py-2 text-[color:var(--admin-text)] text-xs outline-none transition-colors"
                   placeholder="/images/logo.svg o URL">

            <div class="flex items-center gap-2">
                <button type="button"
                        @click="openMedia('{{ $field }}')"
                        class="px-3 py-1.5 bg-[color:var(--admin-accent)]/10 hover:bg-[color:var(--admin-accent)] text-[color:var(--admin-accent)] hover:text-black border border-[color:var(--admin-accent)]/40 rounded-lg text-[11px] font-bold transition-colors flex items-center gap-1.5">
                    <i class="fa-solid fa-folder-open"></i>
                    <span>{{ __('admin.media.choose') }}</span>
                </button>

                <button type="button"
                        x-show="media['{{ $field }}']"
                        @click="clearMedia('{{ $field }}')"
                        class="px-3 py-1.5 bg-[color:var(--admin-surface)] hover:bg-[color:var(--admin-surface-raised)] border border-[color:var(--admin-border)] text-[color:var(--admin-muted)] hover:text-red-400 rounded-lg text-[11px] font-bold transition-colors">
                    Quitar
                </button>
            </div>

            @if($hint)
                <p class="text-[11px] text-[color:var(--admin-muted)]">{{ $hint }}</p>
            @endif
        </div>
    </div>
</div>
