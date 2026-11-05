@extends('admin.layout')

@section('title', __('admin.page_titles.markets'))

@section('content')
@php
    // Traducciones del diálogo de borrado. Se resuelven fuera del atributo
    // @click: Blade copia los atributos de <x-...> como texto literal, así que
    // una directiva @js() en ellos llegaría sin compilar a Alpine.
    $deleteLabels = [
        'market' => __('admin.markets.delete'),
        'marketMessage' => __('admin.markets.delete_confirm'),
        'language' => __('admin.language.delete'),
        'languageMessage' => __('admin.language.delete_confirm'),
    ];
@endphp

<div class="admin-page space-y-6 font-mono text-xs" x-data="{
    showCreateModal: false,
    showEditModal: false,
    showCreateLanguageModal: false,
    deleteLabels: {{ Illuminate\Support\Js::from($deleteLabels)->toHtml() }},
    editMarket: { id: null, code: '', name: '', currency: '', default_locale: '', is_default: false, is_active: true, language_ids: [] },
    openEdit(m, langIds) {
        this.editMarket = {
            id: m.id,
            code: m.code,
            name: m.name,
            flag: m.flag || '🌐',
            currency: m.currency,
            default_locale: m.default_locale,
            is_default: !!m.is_default,
            is_active: !!m.is_active,
            language_ids: langIds || []
        };
        this.showEditModal = true;
    }
}">

    <!-- Unified Datatable Header with Search & Filter -->
    <x-admin.datatable-header
        title="{{ __('admin.markets.title') }}"
        description="Configura regiones de negocio, monedas locales, y asignación de idiomas para enrutamiento inteligente."
        search-placeholder="{{ __('admin.markets.search_placeholder') }}"
        create-modal-event="open-create-market-modal"
        create-label="+ Nuevo Mercado"
    >
        <!-- Custom Filter: Status -->
        <select name="is_active" class="admin-control bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] focus:border-[color:var(--admin-accent)] rounded-lg px-3 py-2 text-[color:var(--admin-text)] text-xs">
            <option value="all">{{ __('admin.markets.all_status') }}</option>
            <option value="1" {{ request('is_active') === '1' ? 'selected' : '' }}>{{ __('admin.markets.active') }}</option>
            <option value="0" {{ request('is_active') === '0' ? 'selected' : '' }}>{{ __('admin.markets.inactive') }}</option>
        </select>
    </x-admin.datatable-header>

    <!-- Markets Table -->
    <x-admin.table
        :empty="$markets->isEmpty()"
        emptyTitle="No hay mercados registrados"
        emptyDescription="No hay mercados que coincidan con la búsqueda o filtros aplicados."
        emptyActionEvent="open-create-market-modal"
        emptyActionLabel="+ Nuevo Mercado"
    >
        <x-slot:header>
            <th class="py-3 px-2 md:hidden w-8"></th>
            <x-admin.sortable-th column="code" label="Código" />
            <x-admin.sortable-th column="name" label="Nombre del Mercado" />
            <x-admin.sortable-th column="currency" label="Moneda" class="hidden lg:table-cell" />
            <th class="py-3 px-4 font-bold uppercase tracking-wider text-[color:var(--admin-muted)] hidden xl:table-cell">{{ __('admin.markets.default_locale') }}</th>
            <th class="py-3 px-4 font-bold uppercase tracking-wider text-[color:var(--admin-muted)] hidden sm:table-cell">{{ __('admin.markets.enabled_languages') }}</th>
            <x-admin.sortable-th column="is_default" label="Principal" class="hidden md:table-cell" />
            <x-admin.sortable-th column="is_active" label="Estado" class="hidden md:table-cell" />
            <th class="py-3 px-4 text-right font-bold uppercase tracking-wider text-[color:var(--admin-muted)]">{{ __('admin.common.actions') }}</th>
        </x-slot:header>

        @foreach($markets as $m)
            <tr class="hover:bg-[color:var(--admin-surface-raised)]/30 transition-colors">
                <td class="py-3.5 px-2 md:hidden text-center w-8">
                    <button type="button" @click="expanded = expanded === $el.closest('tr') ? null : $el.closest('tr')" class="text-[color:var(--admin-muted)] hover:text-[color:var(--admin-text)] p-1 focus:outline-none" aria-label="Ver detalles">
                        <i class="fa-solid text-[10px]" :class="expanded === $el.closest('tr') ? 'fa-chevron-up text-[color:var(--admin-accent)]' : 'fa-chevron-down'"></i>
                    </button>
                </td>
                <td class="py-3.5 px-4 font-bold text-[color:var(--admin-accent)] whitespace-nowrap">
                    <span class="mr-1">{{ $m->flag }}</span>[{{ strtoupper($m->code) }}]
                </td>
                <td class="py-3.5 px-4 font-bold text-[color:var(--admin-text)]">
                    {{ $m->name }}
                </td>
                <td class="py-3.5 px-4 text-[color:var(--admin-text)] font-mono hidden lg:table-cell">{{ $m->currency }}</td>
                <td class="py-3.5 px-4 text-[color:var(--admin-text)] uppercase font-mono hidden xl:table-cell">{{ $m->default_locale }}</td>
                <td class="py-3.5 px-4 hidden sm:table-cell">
                    <div class="flex flex-wrap gap-1">
                        @foreach($m->languages as $lang)
                            <x-admin.chip>
                                {{ $lang->name }} ({{ strtoupper($lang->code) }})
                            </x-admin.chip>
                        @endforeach
                    </div>
                </td>
                <td class="py-3.5 px-4 hidden md:table-cell">
                    @if($m->is_default)
                        <x-admin.chip variant="brand">
                            DEFAULT
                        </x-admin.chip>
                    @else
                        <span class="text-[color:var(--admin-muted)]">—</span>
                    @endif
                </td>
                <td class="py-3.5 px-4 hidden md:table-cell">
                    <x-admin.status-badge :active="$m->is_active" activeText="Activo" inactiveText="Inactivo" />
                </td>
                <td class="py-3.5 px-4 text-right whitespace-nowrap">
                    <x-admin.form-actions :submit="false" class="inline-flex items-center justify-end gap-1.5">
                        <x-admin.action-button
                            icon="fa-solid fa-pen-to-square"
                            tooltip="Editar mercado"
                            variant="primary"
                            @click="openEdit({{ $m->append('flag')->toJson() }}, {{ json_encode($m->languages->pluck('id')->all()) }})"
                        />
                        <form method="POST" action="{{ route('admin.markets.toggle', $m) }}" class="inline">
                            @csrf
                            <x-admin.action-button
                                type="submit"
                                :icon="$m->is_active ? 'fa-solid fa-toggle-on' : 'fa-solid fa-toggle-off'"
                                :tooltip="$m->is_active ? 'Desactivar mercado' : 'Activar mercado'"
                                :variant="$m->is_active ? 'warning' : 'default'"
                            />
                        </form>
                        <x-admin.action-button
                            icon="fa-solid fa-trash-can"
                            :tooltip="__('admin.markets.delete')"
                            variant="danger"
                            @click="$dispatch('confirm-delete', {
                                action: '{{ route('admin.markets.destroy', $m) }}',
                                title: deleteLabels.market,
                                message: deleteLabels.marketMessage
                            })"
                        />
                    </x-admin.form-actions>
                </td>
            </tr>

            <x-admin.table-row-detail colSpan="9">
                <x-admin.table-row-detail-item label="Moneda">
                    <span class="font-mono text-[color:var(--admin-text)]">{{ $m->currency }}</span>
                </x-admin.table-row-detail-item>
                <x-admin.table-row-detail-item label="Locale por Defecto">
                    <span class="uppercase font-mono text-[color:var(--admin-text)]">{{ $m->default_locale }}</span>
                </x-admin.table-row-detail-item>
                <x-admin.table-row-detail-item label="Idiomas Habilitados">
                    <div class="flex flex-wrap gap-1">
                        @foreach($m->languages as $lang)
                            <x-admin.chip>{{ $lang->name }} ({{ strtoupper($lang->code) }})</x-admin.chip>
                        @endforeach
                    </div>
                </x-admin.table-row-detail-item>
                <x-admin.table-row-detail-item label="Principal / Default">
                    @if($m->is_default)
                        <x-admin.chip variant="brand">{{ __('admin.markets.default') }}</x-admin.chip>
                    @else
                        <span class="text-[color:var(--admin-muted)]">{{ __('admin.markets.no') }}</span>
                    @endif
                </x-admin.table-row-detail-item>
                <x-admin.table-row-detail-item label="Estado">
                    <x-admin.status-badge :active="$m->is_active" activeText="Activo" inactiveText="Inactivo" />
                </x-admin.table-row-detail-item>
            </x-admin.table-row-detail>
        @endforeach

        <x-slot:pagination>
            <x-admin.datatable-pagination :paginator="$markets" />
        </x-slot:pagination>
    </x-admin.table>

    <!-- System Languages Card (Dynamic Detection) -->
    <x-admin.form-card class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-4 pb-3 border-b border-[color:var(--admin-border)]">
            <div>
                <h3 class="text-sm font-bold text-[color:var(--admin-text)] flex items-center gap-2">
                    <span class="text-[color:var(--admin-accent)]">&gt;_</span>
                    <span>{{ __('admin.language.system_title') }}</span>
                </h3>
                <p class="text-[color:var(--admin-muted)] text-[11px] mt-0.5">
                    Activa, desactiva o añade idiomas globales. Los formularios y selectores los detectan automáticamente.
                </p>
            </div>
            <x-admin.button variant="primary" type="button"
                    @click="showCreateLanguageModal = true"
                    class="text-xs py-1.5 px-3">
                <span>{{ __('admin.language.add') }}</span>
            </x-admin.button>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="bg-[color:var(--admin-surface)] border-b border-[color:var(--admin-border)] text-[color:var(--admin-muted)]">
                    <tr>
                        <th class="py-2.5 px-2 md:hidden w-8"></th>
                        <th class="py-2.5 px-4 font-bold uppercase tracking-wider text-[color:var(--admin-muted)] text-[10px]">{{ __('admin.language.flag') }}</th>
                        <th class="py-2.5 px-4 font-bold uppercase tracking-wider text-[color:var(--admin-muted)] text-[10px]">{{ __('admin.language.code') }}</th>
                        <th class="py-2.5 px-4 font-bold uppercase tracking-wider text-[color:var(--admin-muted)] text-[10px]">{{ __('admin.language.name') }}</th>
                        <th class="py-2.5 px-4 font-bold uppercase tracking-wider text-[color:var(--admin-muted)] text-[10px] hidden sm:table-cell">{{ __('admin.language.native_name') }}</th>
                        <th class="py-2.5 px-4 font-bold uppercase tracking-wider text-[color:var(--admin-muted)] text-[10px] hidden md:table-cell">{{ __('admin.language.direction') }}</th>
                        <th class="py-2.5 px-4 font-bold uppercase tracking-wider text-[color:var(--admin-muted)] text-[10px] hidden sm:table-cell">{{ __('admin.common.status') }}</th>
                        <th class="py-2.5 px-4 text-right font-bold uppercase tracking-wider text-[color:var(--admin-muted)] text-[10px]">{{ __('admin.common.actions') }}</th>
                    </tr>
                </thead>
                <tbody x-data="{ expanded: null }" class="divide-y divide-[color:var(--admin-border)]/60">
                    @foreach($allLanguages as $lang)
                        <tr x-data="{ langEditOpen: false }" class="hover:bg-[color:var(--admin-surface-raised)]/30 transition-colors">
                            <td class="py-2.5 px-2 md:hidden text-center w-8">
                                <button type="button" @click="expanded = expanded === $el.closest('tr') ? null : $el.closest('tr')" class="text-[color:var(--admin-muted)] hover:text-[color:var(--admin-text)] p-1 focus:outline-none" aria-label="Ver detalles">
                                    <i class="fa-solid text-[10px]" :class="expanded === $el.closest('tr') ? 'fa-chevron-up text-[color:var(--admin-accent)]' : 'fa-chevron-down'"></i>
                                </button>
                            </td>
                            <td class="py-2.5 px-4 text-base leading-none">{{ $lang->flag }}</td>
                            <td class="py-2.5 px-4 font-bold text-[color:var(--admin-accent)]">
                                [{{ strtoupper($lang->code) }}]
                            </td>
                            <td class="py-2.5 px-4 font-bold text-[color:var(--admin-text)]">
                                {{ $lang->name }}
                            </td>
                            <td class="py-2.5 px-4 text-[color:var(--admin-text)] hidden sm:table-cell">
                                {{ $lang->native_name }}
                            </td>
                            <td class="py-2.5 px-4 text-[color:var(--admin-muted)] uppercase hidden md:table-cell">
                                {{ $lang->direction }}
                            </td>
                            <td class="py-2.5 px-4 hidden sm:table-cell">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold {{ $lang->is_active ? 'bg-[color:var(--admin-accent)]/10 text-[color:var(--admin-accent)] border border-[color:var(--admin-accent)]/30' : 'bg-[color:var(--admin-surface-raised)] text-[color:var(--admin-muted)]' }}">
                                    {{ $lang->is_active ? 'ACTIVO' : 'INACTIVO' }}
                                </span>
                            </td>
                            <td class="py-2.5 px-4 text-right whitespace-nowrap">
                                <x-admin.form-actions :submit="false" class="inline-flex items-center justify-end gap-1.5">
                                    <x-admin.action-button
                                        type="button"
                                        @click="langEditOpen = true"
                                        icon="fa-solid fa-pen-to-square"
                                        :tooltip="__('admin.language.edit')"
                                        variant="primary"
                                    />
                                    <form method="POST" action="{{ route('admin.languages.toggle', $lang) }}" class="inline">
                                        @csrf
                                        <x-admin.action-button
                                            type="submit"
                                            :icon="$lang->is_active ? 'fa-solid fa-toggle-on' : 'fa-solid fa-toggle-off'"
                                            :tooltip="$lang->is_active ? __('admin.language.deactivate') : __('admin.language.activate')"
                                            :variant="$lang->is_active ? 'warning' : 'default'"
                                        />
                                    </form>
                                    <x-admin.action-button
                                        icon="fa-solid fa-trash-can"
                                        :tooltip="__('admin.language.delete')"
                                        variant="danger"
                                        @click="$dispatch('confirm-delete', {
                                            action: '{{ route('admin.languages.destroy', $lang) }}',
                                            title: deleteLabels.language,
                                            message: deleteLabels.languageMessage
                                        })"
                                    />
                                </x-admin.form-actions>

                                <x-admin.modal
                                    show="langEditOpen"
                                    onClose="langEditOpen = false"
                                    :title="__('admin.language.edit_title')"
                                    :subtitle="__('admin.language.edit_subtitle')"
                                    icon="fa-solid fa-language"
                                    maxWidth="md"
                                >
                                    <form method="POST" action="{{ route('admin.languages.update', $lang) }}" class="space-y-4 text-xs">
                                        @csrf
                                        @method('PUT')
                                        <div>
                                            <label class="admin-field-label block font-bold text-[color:var(--admin-muted)] uppercase mb-1">{{ __('admin.language.flag') }}</label>
                                            <x-admin.emoji-picker name="flag" :value="$lang->flag" />
                                            <p class="text-[10px] text-[color:var(--admin-muted)] mt-1">{{ __('admin.language.flag_hint') }}</p>
                                        </div>
                                        <div>
                                            <label class="admin-field-label block font-bold text-[color:var(--admin-muted)] uppercase mb-1">{{ __('admin.language.name') }}</label>
                                            <input type="text" name="name" value="{{ old('name', $lang->name) }}" required class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] focus:border-[color:var(--admin-accent)] rounded-lg px-3 py-2 text-[color:var(--admin-text)]">
                                        </div>
                                        <div>
                                            <label class="admin-field-label block font-bold text-[color:var(--admin-muted)] uppercase mb-1">{{ __('admin.language.native_name_label') }}</label>
                                            <input type="text" name="native_name" value="{{ old('native_name', $lang->native_name) }}" required class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] focus:border-[color:var(--admin-accent)] rounded-lg px-3 py-2 text-[color:var(--admin-text)]">
                                        </div>
                                        <div>
                                            <label class="admin-field-label block font-bold text-[color:var(--admin-muted)] uppercase mb-1">{{ __('admin.language.reading_direction') }}</label>
                                            <select name="direction" class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] focus:border-[color:var(--admin-accent)] rounded-lg px-3 py-2 text-[color:var(--admin-text)]">
                                                <option value="ltr" {{ old('direction', $lang->direction) === 'ltr' ? 'selected' : '' }}>{{ __('admin.language.ltr') }}</option>
                                                <option value="rtl" {{ old('direction', $lang->direction) === 'rtl' ? 'selected' : '' }}>{{ __('admin.language.rtl') }}</option>
                                            </select>
                                        </div>
                                        <label class="flex items-center gap-2 cursor-pointer">
                                            <input type="hidden" name="is_active" value="0">
                                            <input type="checkbox" name="is_active" value="1" {{ old('is_active', $lang->is_active) ? 'checked' : '' }} class="rounded bg-[color:var(--admin-surface)] border-[color:var(--admin-border)] text-[color:var(--admin-accent)] focus:ring-[color:var(--admin-accent)]">
                                            <span class="text-[color:var(--admin-text)]">{{ __('admin.language.active_for_translations') }}</span>
                                        </label>
                                        <x-admin.form-actions :submit="false" class="flex justify-end gap-3 pt-3 border-t border-[color:var(--admin-border)]">
                                            <button type="button" @click="langEditOpen = false" class="px-4 py-2 bg-[color:var(--admin-surface-raised)] text-[color:var(--admin-muted)] hover:text-[color:var(--admin-text)] rounded-lg">{{ __('admin.actions.cancel') }}</button>
                                            <x-admin.button variant="primary" type="submit" class="">{{ __('admin.actions.save') }}</x-admin.button>
                                        </x-admin.form-actions>
                                    </form>
                                </x-admin.modal>
                            </td>
                        </tr>

                        <x-admin.table-row-detail colSpan="7">
                            <x-admin.table-row-detail-item label="Nombre Nativo">
                                <span class="text-[color:var(--admin-text)]">{{ $lang->native_name }}</span>
                            </x-admin.table-row-detail-item>
                            <x-admin.table-row-detail-item label="Dirección">
                                <span class="uppercase text-[color:var(--admin-muted)]">{{ $lang->direction }}</span>
                            </x-admin.table-row-detail-item>
                            <x-admin.table-row-detail-item label="Estado">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold {{ $lang->is_active ? 'bg-[color:var(--admin-accent)]/10 text-[color:var(--admin-accent)] border border-[color:var(--admin-accent)]/30' : 'bg-[color:var(--admin-surface-raised)] text-[color:var(--admin-muted)]' }}">
                                    {{ $lang->is_active ? 'ACTIVO' : 'INACTIVO' }}
                                </span>
                            </x-admin.table-row-detail-item>
                        </x-admin.table-row-detail>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-admin.form-card>

    <!-- Create Market Modal -->
    <div x-data="{ open: false }" @open-create-market-modal.window="open = true">
        <x-admin.modal
            show="open"
            title="{{ __('admin.markets.create_title') }}"
            subtitle="{{ __('admin.markets.create_subtitle') }}"
            icon="fa-solid fa-earth-americas"
            maxWidth="lg"
        >
            <form method="POST" action="{{ route('admin.markets.store') }}" class="space-y-4 text-xs">
                @csrf
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="admin-field-label block font-bold text-[color:var(--admin-muted)] uppercase mb-1">{{ __('admin.markets.market_code') }} *</label>
                        <input type="text" name="code" required maxlength="5" placeholder="{{ __('admin.markets.code_placeholder') }}" class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] focus:border-[color:var(--admin-accent)] rounded-lg px-3 py-2 text-[color:var(--admin-text)] uppercase font-bold">
                    </div>
                    <div>
                        <label class="admin-field-label block font-bold text-[color:var(--admin-muted)] uppercase mb-1">{{ __('admin.markets.market_name') }} *</label>
                        <input type="text" name="name" required placeholder="{{ __('admin.markets.market_name_placeholder') }}" class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] focus:border-[color:var(--admin-accent)] rounded-lg px-3 py-2 text-[color:var(--admin-text)]">
                    </div>
                    <div class="col-span-2">
                        <label class="admin-field-label block font-bold text-[color:var(--admin-muted)] uppercase mb-1">{{ __('admin.markets.flag') }}</label>
                        <x-admin.emoji-picker name="flag" :value="old('flag')" />
                        <p class="text-[10px] text-[color:var(--admin-muted)] mt-1">{{ __('admin.markets.flag_hint') }}</p>
                    </div>
                    <div>
                        <label class="admin-field-label block font-bold text-[color:var(--admin-muted)] uppercase mb-1">{{ __('admin.markets.currency') }} *</label>
                        <input type="text" name="currency" required maxlength="5" placeholder="EUR" class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] focus:border-[color:var(--admin-accent)] rounded-lg px-3 py-2 text-[color:var(--admin-text)] uppercase">
                    </div>
                    <div>
                        <label class="admin-field-label block font-bold text-[color:var(--admin-muted)] uppercase mb-1">{{ __('admin.markets.default_locale_label') }} *</label>
                        <x-admin.language-select name="default_locale" value-key="code" placeholder="{{ __('admin.language.select') }}" />
                    </div>
                </div>

                <div>
                    <label class="admin-field-label block font-bold text-[color:var(--admin-muted)] uppercase mb-1.5">{{ __('admin.markets.assigned_languages') }}</label>
                    <div class="space-y-1 bg-[color:var(--admin-surface)] p-3 rounded-lg border border-[color:var(--admin-border)]">
                        @foreach($allLanguages as $lang)
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" name="language_ids[]" value="{{ $lang->id }}" class="rounded bg-[color:var(--admin-surface)] border-[color:var(--admin-border)] text-[color:var(--admin-accent)] focus:ring-[color:var(--admin-accent)]">
                                <span class="text-[color:var(--admin-text)]">{{ $lang->name }} ({{ $lang->native_name }})</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="flex items-center justify-between pt-2">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="is_active" value="1" checked class="rounded bg-[color:var(--admin-surface)] border-[color:var(--admin-border)] text-[color:var(--admin-accent)] focus:ring-[color:var(--admin-accent)]">
                        <span class="text-[color:var(--admin-text)]">{{ __('admin.markets.market_active') }}</span>
                    </label>
                </div>

                <x-admin.form-actions :submit="false" class="flex justify-end gap-3 pt-4 border-t border-[color:var(--admin-border)]">
                    <button type="button" @click="open = false" class="px-4 py-2 bg-[color:var(--admin-surface-raised)] text-[color:var(--admin-muted)] hover:text-[color:var(--admin-text)] rounded-lg">{{ __('admin.actions.cancel') }}</button>
                    <x-admin.button variant="primary" type="submit" class="">{{ __('admin.markets.create_button') }}</x-admin.button>
                </x-admin.form-actions>
            </form>
        </x-admin.modal>
    </div>

    <!-- Edit Market Modal -->
    <x-admin.modal
        show="showEditModal"
        onClose="showEditModal = false"
        maxWidth="lg"
        icon="fa-solid fa-pen-to-square"
    >
        <x-slot:header>
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-[color:var(--admin-accent)]/10 border border-[color:var(--admin-accent)]/30 flex items-center justify-center text-[color:var(--admin-accent)] shrink-0">
                    <i class="fa-solid fa-pen-to-square"></i>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-[color:var(--admin-text)] flex items-center gap-2">
                        <span>&gt; {{ __('admin.markets.edit_title') }}</span>
                        <span class="text-[color:var(--admin-accent)]" x-text="editMarket.code ? editMarket.code.toUpperCase() : ''"></span>
                    </h3>
                    <p class="text-[11px] text-[color:var(--admin-muted)]">{{ __('admin.markets.edit_subtitle') }}</p>
                </div>
            </div>
        </x-slot:header>

        <form method="POST" :action="'{{ url(config('cms.admin_path', 'admin') . '/markets') }}/' + editMarket.id" class="space-y-4 text-xs">
            @csrf
            @method('PUT')
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="admin-field-label block font-bold text-[color:var(--admin-muted)] uppercase mb-1">{{ __('admin.markets.market_name') }} *</label>
                    <input type="text" name="name" x-model="editMarket.name" required class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] focus:border-[color:var(--admin-accent)] rounded-lg px-3 py-2 text-[color:var(--admin-text)]">
                </div>
                <div>
                    <label class="admin-field-label block font-bold text-[color:var(--admin-muted)] uppercase mb-1">{{ __('admin.markets.currency') }} *</label>
                    <input type="text" name="currency" x-model="editMarket.currency" required maxlength="5" class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] focus:border-[color:var(--admin-accent)] rounded-lg px-3 py-2 text-[color:var(--admin-text)] uppercase">
                </div>
                <div>
                    <label class="admin-field-label block font-bold text-[color:var(--admin-muted)] uppercase mb-1">{{ __('admin.markets.default_locale_label') }} *</label>
                    <x-admin.language-select name="default_locale" value-key="code" placeholder="{{ __('admin.language.select') }}"
                        x-init="hydrate(editMarket.default_locale || null); $watch('showEditModal', opened => { if (opened) hydrate(editMarket.default_locale || null); })" />
                </div>
                <div class="col-span-2">
                    <label class="admin-field-label block font-bold text-[color:var(--admin-muted)] uppercase mb-1">{{ __('admin.markets.flag') }}</label>
                    <x-admin.emoji-picker name="flag" :bind="'editMarket.flag'" />
                    <p class="text-[10px] text-[color:var(--admin-muted)] mt-1">{{ __('admin.markets.flag_hint') }}</p>
                </div>
            </div>

            <div>
                <label class="admin-field-label block font-bold text-[color:var(--admin-muted)] uppercase mb-1.5">{{ __('admin.markets.assigned_languages') }}</label>
                <div class="space-y-1 bg-[color:var(--admin-surface)] p-3 rounded-lg border border-[color:var(--admin-border)]">
                    @foreach($allLanguages as $lang)
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="language_ids[]" value="{{ $lang->id }}" :checked="editMarket.language_ids.includes({{ $lang->id }})" class="rounded bg-[color:var(--admin-surface)] border-[color:var(--admin-border)] text-[color:var(--admin-accent)] focus:ring-[color:var(--admin-accent)]">
                            <span class="text-[color:var(--admin-text)]">{{ $lang->name }} ({{ $lang->native_name }})</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="flex items-center justify-between pt-2">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="is_active" value="1" :checked="editMarket.is_active" class="rounded bg-[color:var(--admin-surface)] border-[color:var(--admin-border)] text-[color:var(--admin-accent)] focus:ring-[color:var(--admin-accent)]">
                    <span class="text-[color:var(--admin-text)]">{{ __('admin.markets.market_active') }}</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="is_default" value="1" :checked="editMarket.is_default" class="rounded bg-[color:var(--admin-surface)] border-[color:var(--admin-border)] text-[color:var(--admin-accent)] focus:ring-[color:var(--admin-accent)]">
                    <span class="text-[color:var(--admin-text)]">{{ __('admin.markets.market_principal') }}</span>
                </label>
            </div>

            <x-admin.form-actions :submit="false" class="flex justify-end gap-3 pt-4 border-t border-[color:var(--admin-border)]">
                <button type="button" @click="showEditModal = false" class="px-4 py-2 bg-[color:var(--admin-surface-raised)] text-[color:var(--admin-muted)] hover:text-[color:var(--admin-text)] rounded-lg">{{ __('admin.actions.cancel') }}</button>
                <x-admin.button variant="primary" type="submit" class="">{{ __('admin.actions.save') }}</x-admin.button>
            </x-admin.form-actions>
        </form>
    </x-admin.modal>

    <!-- Create Language Modal -->
    <x-admin.modal
        show="showCreateLanguageModal"
        onClose="showCreateLanguageModal = false"
        title="{{ __('admin.language.register_title') }}"
        subtitle="{{ __('admin.language.register_subtitle') }}"
        icon="fa-solid fa-language"
        maxWidth="lg"
    >
        <form method="POST" action="{{ route('admin.languages.store') }}" class="space-y-4 text-xs">
            @csrf
            <div class="grid grid-cols-2 gap-3">
                <div class="col-span-2">
                    <label class="admin-field-label block font-bold text-[color:var(--admin-muted)] uppercase mb-1">{{ __('admin.language.flag') }}</label>
                    <x-admin.emoji-picker name="flag" :value="old('flag')" />
                    <p class="text-[10px] text-[color:var(--admin-muted)] mt-1">{{ __('admin.language.flag_hint') }}</p>
                </div>
                <div>
                    <label class="admin-field-label block font-bold text-[color:var(--admin-muted)] uppercase mb-1">{{ __('admin.language.iso_code') }} *</label>
                    <input type="text" name="code" required maxlength="5" placeholder="{{ __('admin.language.iso_placeholder') }}" class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] focus:border-[color:var(--admin-accent)] rounded-lg px-3 py-2 text-[color:var(--admin-text)] font-bold lowercase">
                </div>
                <div>
                    <label class="admin-field-label block font-bold text-[color:var(--admin-muted)] uppercase mb-1">{{ __('admin.language.name_international') }} *</label>
                    <input type="text" name="name" required placeholder="{{ __('admin.language.name_placeholder') }}" class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] focus:border-[color:var(--admin-accent)] rounded-lg px-3 py-2 text-[color:var(--admin-text)]">
                </div>
                <div>
                    <label class="admin-field-label block font-bold text-[color:var(--admin-muted)] uppercase mb-1">{{ __('admin.language.native_name_label') }} *</label>
                    <input type="text" name="native_name" required placeholder="{{ __('admin.language.native_placeholder') }}" class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] focus:border-[color:var(--admin-accent)] rounded-lg px-3 py-2 text-[color:var(--admin-text)]">
                </div>
                <div>
                    <label class="admin-field-label block font-bold text-[color:var(--admin-muted)] uppercase mb-1">{{ __('admin.language.reading_direction') }} *</label>
                    <select name="direction" class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] focus:border-[color:var(--admin-accent)] rounded-lg px-3 py-2 text-[color:var(--admin-text)]">
                        <option value="ltr">{{ __('admin.language.ltr') }}</option>
                        <option value="rtl">{{ __('admin.language.rtl') }}</option>
                    </select>
                </div>
            </div>

            <div class="flex items-center justify-between pt-2">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="is_active" value="1" checked class="rounded bg-[color:var(--admin-surface)] border-[color:var(--admin-border)] text-[color:var(--admin-accent)] focus:ring-[color:var(--admin-accent)]">
                    <span class="text-[color:var(--admin-text)]">{{ __('admin.language.active_for_translations') }}</span>
                </label>
            </div>

            <x-admin.form-actions :submit="false" class="flex justify-end gap-3 pt-4 border-t border-[color:var(--admin-border)]">
                <button type="button" @click="showCreateLanguageModal = false" class="px-4 py-2 bg-[color:var(--admin-surface-raised)] text-[color:var(--admin-muted)] hover:text-[color:var(--admin-text)] rounded-lg">{{ __('admin.actions.cancel') }}</button>
                <x-admin.button variant="primary" type="submit" class="">{{ __('admin.language.create_button') }}</x-admin.button>
            </x-admin.form-actions>
        </form>
    </x-admin.modal>

</div>
@endsection
