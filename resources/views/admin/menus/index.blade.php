@extends('admin.layout')

@section('title', __('admin.page_titles.menus'))

@section('content')
<div class="admin-page space-y-6 font-mono text-xs admin-theme-root"
     x-data="{ selectedMenu: null, showCreateMenu: false, showAddItem: false, activeTab: '{{ $activeTab ?? 'menus' }}' }"
     @open-create-menu-modal.window="showCreateMenu = true">

    <!-- Tab Navigation -->
    <div class="flex items-center gap-2 border-b border-[color:var(--admin-border)] pb-2">
        <button type="button"
                @click="activeTab = 'menus'"
                :class="activeTab === 'menus' ? 'bg-[color:var(--admin-accent)] text-black font-bold admin-tab-active' : 'text-[color:var(--admin-muted)] hover:text-[color:var(--admin-text)] admin-tab-inactive'"
                class="px-4 py-2 rounded-lg text-xs transition-all flex items-center gap-2">
            <i class="fa-solid fa-bars"></i>
            <span>{{ __('admin.menus.structure') }}</span>
            <span class="px-1.5 py-0.5 rounded text-[10px] bg-[color:var(--admin-surface)]/40 admin-chip-count">{{ $menus->total() }}</span>
        </button>
        <button type="button"
                @click="activeTab = 'zones'"
                :class="activeTab === 'zones' ? 'bg-[color:var(--admin-accent)] text-black font-bold admin-tab-active' : 'text-[color:var(--admin-muted)] hover:text-[color:var(--admin-text)] admin-tab-inactive'"
                class="px-4 py-2 rounded-lg text-xs transition-all flex items-center gap-2">
            <i class="fa-solid fa-map-location-dot"></i>
            <span>{{ __('admin.menus.zones_tab') }}</span>
            <span class="px-1.5 py-0.5 rounded text-[10px] bg-[color:var(--admin-surface)]/40 admin-chip-count">{{ count($registeredZones ?? []) }}</span>
        </button>
    </div>

    <!-- TAB 1: MENUS LIST -->
    <div x-show="activeTab === 'menus'" class="space-y-6">
        <!-- Unified Datatable Header -->
        <x-admin.datatable-header
            title="{{ __('admin.menus.title') }}"
            description="Estructura las zonas de navegación (header, footer, sidebar, mobile) para cada mercado e idioma."
            searchPlaceholder="Buscar menús por nombre o zona..."
            createModalEvent="open-create-menu-modal"
            createLabel="+ Nuevo Menú"
        />

        <!-- Menus Table -->
        <x-admin.table
            :empty="$menus->isEmpty()"
            emptyTitle="No hay menús registrados"
            emptyDescription="No se encontraron menús con los filtros seleccionados o aún no has creado ninguno."
            emptyActionEvent="open-create-menu-modal"
            emptyActionLabel="+ Nuevo Menú"
        >
            <x-slot:header>
                <th class="py-3 px-2 md:hidden w-8"></th>
                <x-admin.sortable-th column="name" label="Nombre del Menú" />
                <x-admin.sortable-th column="slug" label="Identificador" class="hidden md:table-cell" />
                <x-admin.sortable-th column="location" label="Ubicación / Zona" class="hidden sm:table-cell" />
                <th class="py-3 px-4 font-bold uppercase tracking-wider text-[color:var(--admin-muted)] hidden lg:table-cell">{{ __('admin.menus.col_items') }}</th>
                <th class="py-3 px-4 font-bold uppercase tracking-wider text-[color:var(--admin-muted)] hidden lg:table-cell">{{ __('admin.menus.col_markets') }}</th>
                <th class="py-3 px-4 font-bold uppercase tracking-wider text-[color:var(--admin-muted)] hidden xl:table-cell">{{ __('admin.menus.col_languages') }}</th>
                <th class="py-3 px-4 text-right font-bold uppercase tracking-wider text-[color:var(--admin-muted)]">{{ __('admin.menus.col_actions') }}</th>
            </x-slot:header>

            @foreach($menus as $m)
                <tr class="hover:bg-[color:var(--admin-surface-raised)]/30 transition-colors">
                    <!-- Mobile Chevron Toggle -->
                    <td class="py-3.5 px-2 md:hidden text-center w-8">
                        <button type="button"
                                @click="expanded = expanded === $el.closest('tr') ? null : $el.closest('tr')"
                                class="w-6 h-6 rounded-md flex items-center justify-center text-[color:var(--admin-muted)] hover:text-[color:var(--admin-text)] hover:bg-[color:var(--admin-surface-raised)] transition-colors"
                                :title="expanded ? @js(__('admin.menus.hide_details')) : @js(__('admin.menus.show_details'))">
                            <i class="fa-solid text-[10px] transition-transform duration-200"
                               :class="expanded === $el.closest('tr') ? 'fa-chevron-up text-[color:var(--admin-accent)]' : 'fa-chevron-down'"></i>
                        </button>
                    </td>

                    <td class="py-3.5 px-4 font-bold text-[color:var(--admin-text)]">{{ $m->name }}</td>
                    <td class="py-3.5 px-4 text-[color:var(--admin-accent)] font-mono hidden md:table-cell">#{{ $m->slug }}</td>
                    <td class="py-3.5 px-4 hidden sm:table-cell">
                        <x-admin.chip variant="default">
                            {{ $m->location }}
                        </x-admin.chip>
                    </td>
                    <td class="py-3.5 px-4 text-[color:var(--admin-text)] hidden lg:table-cell">
                        <x-admin.chip>
                            {{ $m->items->count() }} elementos
                        </x-admin.chip>
                    </td>
                    <td class="py-3.5 px-4 hidden lg:table-cell">
                        <x-admin.market-chips :markets="$m->markets" empty="Global" />
                    </td>
                    <td class="py-3.5 px-4 hidden xl:table-cell">
                        <x-admin.locale-chips :locales="$m->items->flatMap(fn ($item) => $item->translations->pluck('locale'))" />
                    </td>
                    <td class="py-3.5 px-4 text-right">
                        <div class="inline-flex items-center justify-end gap-1.5">
                            <x-admin.action-button
                                type="button"
                                @click="selectedMenu = {{ json_encode($m) }}"
                                icon="fa-solid fa-link"
                                tooltip="Administrar Enlaces"
                                variant="primary"
                            />
                            <x-admin.action-button
                                type="button"
                                @click="$dispatch('confirm-delete', {
                                    action: '{{ route('admin.menus.destroy', $m) }}',
                                    title: 'Eliminar Menú',
                                    message: '¿Estás seguro de eliminar el menú {{ addslashes($m->name) }} y todos sus enlaces?'
                                })"
                                icon="fa-solid fa-trash-can"
                                tooltip="Eliminar Menú"
                                variant="danger"
                            />
                        </div>
                    </td>
                </tr>

                <!-- Mobile Accordion Detail Row -->
                <x-admin.table-row-detail>
                    <x-admin.table-row-detail-item label="Identificador">
                        <span class="font-mono text-[color:var(--admin-accent)]">#{{ $m->slug }}</span>
                    </x-admin.table-row-detail-item>
                    <x-admin.table-row-detail-item label="Ubicación / Zona">
                        <x-admin.chip variant="default">{{ $m->location }}</x-admin.chip>
                    </x-admin.table-row-detail-item>
                    <x-admin.table-row-detail-item label="Elementos">
                        <x-admin.chip>{{ __('admin.menus.links_count', ['count' => $m->items->count()]) }}</x-admin.chip>
                    </x-admin.table-row-detail-item>
                </x-admin.table-row-detail>
            @endforeach

            <x-slot:pagination>
                <x-admin.datatable-pagination :paginator="$menus" />
            </x-slot:pagination>
        </x-admin.table>
    </div>

    <!-- TAB 2: ZONE MANAGER -->
    <div x-show="activeTab === 'zones'" style="display: none;">
        <form method="POST" action="{{ route('admin.menus.zones.store') }}" class="space-y-4">
            @csrf
            <x-admin.form-card class="overflow-hidden">
                <div class="p-4 border-b border-[color:var(--admin-border)] bg-[color:var(--admin-surface)] admin-card-header flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <h3 class="text-sm font-bold text-[color:var(--admin-text)] admin-card-title flex items-center gap-2">
                            <i class="fa-solid fa-map-location-dot text-[color:var(--admin-accent)]"></i>
                            <span>{{ __('admin.menus.zones_title') }}</span>
                        </h3>
                        <p class="text-[11px] text-[color:var(--admin-muted)] admin-card-desc">{{ __('admin.menus.zones_desc') }}</p>
                    </div>
                    <x-admin.button variant="primary" type="submit" class="text-xs  flex items-center gap-2">
                        <i class="fa-solid fa-floppy-disk"></i>
                        <span>{{ __('admin.menus.save_zones') }}</span>
                    </x-admin.button>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-[color:var(--admin-surface)] border-b border-[color:var(--admin-border)] text-[color:var(--admin-muted)]">
                            <tr>
                                <th class="py-3 px-4 font-bold uppercase tracking-wider">{{ __('admin.menus.col_zone') }}</th>
                                <th class="py-3 px-4 font-bold uppercase tracking-wider hidden sm:table-cell">{{ __('admin.menus.col_identifier') }}</th>
                                <th class="py-3 px-4 font-bold uppercase tracking-wider">{{ __('admin.menus.col_assigned_menu') }}</th>
                                <th class="py-3 px-4 font-bold uppercase tracking-wider hidden md:table-cell">{{ __('admin.menus.col_status') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[color:var(--admin-border)]/60">
                            @foreach(($registeredZones ?? []) as $zoneKey => $zoneInfo)
                                @php
                                    $currentMenuId = $zoneAssignments[$zoneKey] ?? null;
                                    $assignedMenu = $currentMenuId ? ($allMenus ?? collect())->firstWhere('id', $currentMenuId) : null;
                                @endphp
                                <tr class="hover:bg-[color:var(--admin-surface-raised)]/20 transition-colors">
                                    <td class="py-3.5 px-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-lg bg-[color:var(--admin-accent)]/10 border border-[color:var(--admin-accent)]/30 text-[color:var(--admin-accent)] flex items-center justify-center shrink-0 admin-icon-box">
                                                <i class="{{ $zoneInfo['icon'] }}"></i>
                                            </div>
                                            <div>
                                                <div class="font-bold text-[color:var(--admin-text)] admin-text-primary">{{ $zoneInfo['name'] }}</div>
                                                <div class="text-[11px] text-[color:var(--admin-muted)] admin-text-muted">{{ $zoneInfo['description'] }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-3.5 px-4 font-mono text-[color:var(--admin-accent)] hidden sm:table-cell">
                                        #{{ $zoneKey }}
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <select name="zones[{{ $zoneKey }}]" class="admin-control w-full max-w-xs bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] text-[color:var(--admin-text)] focus:border-[color:var(--admin-accent)] rounded-lg px-3 py-1.5 text-xs admin-input">
                                            <option value="none">{{ __('admin.menus.none_disabled') }}</option>
                                            @foreach(($allMenus ?? []) as $optMenu)
                                                <option value="{{ $optMenu->id }}" {{ $currentMenuId == $optMenu->id ? 'selected' : '' }}>
                                                    {{ $optMenu->name }} ({{ $optMenu->items->count() }} enlaces)
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="py-3.5 px-4 hidden md:table-cell">
                                        @if($assignedMenu)
                                            <x-admin.status-badge :active="true" activeText="Activo" />
                                        @else
                                            <x-admin.chip variant="default">{{ __('admin.menus.unassigned') }}</x-admin.chip>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-admin.form-card>
        </form>
    </div>

    <!-- Create Menu Modal -->
    <x-admin.modal
        show="showCreateMenu"
        title="{{ __('admin.menus.create_title') }}"
        subtitle="{{ __('admin.menus.create_subtitle') }}"
        icon="fa-solid fa-bars"
        maxWidth="md"
        @open-create-menu-modal.window="showCreateMenu = true"
    >
        <form method="POST" action="{{ route('admin.menus.store') }}" class="space-y-4 text-xs">
            @csrf
            <div>
                <label class="admin-field-label block font-bold text-[color:var(--admin-muted)] uppercase mb-1">{{ __('admin.menus.menu_name') }}</label>
                <input type="text" name="name" required placeholder="{{ __('admin.menus.menu_name_placeholder') }}" class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] text-[color:var(--admin-text)] focus:border-[color:var(--admin-accent)] rounded-lg px-3 py-2 admin-input">
            </div>
            <div>
                <label class="admin-field-label block font-bold text-[color:var(--admin-muted)] uppercase mb-1">{{ __('admin.menus.menu_slug') }}</label>
                <input type="text" name="slug" required placeholder="{{ __('admin.menus.menu_slug_placeholder') }}" class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] text-[color:var(--admin-text)] focus:border-[color:var(--admin-accent)] rounded-lg px-3 py-2 font-mono admin-input">
            </div>
            <div>
                <label class="admin-field-label block font-bold text-[color:var(--admin-muted)] uppercase mb-1">{{ __('admin.menus.menu_zone') }}</label>
                <select name="location" class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] text-[color:var(--admin-text)] focus:border-[color:var(--admin-accent)] rounded-lg px-3 py-2 admin-input">
                    @foreach(config('cms.options.menu_zones') as $zoneValue => $zoneLabel)
                        <option value="{{ $zoneValue }}">{{ __($zoneLabel) }}</option>
                    @endforeach
                </select>
            </div>
            <x-admin.form-actions :submit="false" class="flex justify-end gap-3 pt-4 border-t border-[color:var(--admin-border)]">
                <x-admin.button x-on:click="showCreateMenu = false">{{ __('admin.actions.cancel') }}</x-admin.button>
                <x-admin.button variant="primary" type="submit" class="">{{ __('admin.menus.save_menu') }}</x-admin.button>
            </x-admin.form-actions>
        </form>
    </x-admin.modal>

    <!-- Manage Menu Items Modal -->
    <div x-data="{
        itemLocale: @js($defaultLocale ?? 'es'),
        locales: @js(collect($languages ?? [])->pluck('code')->values()->all()),
        newItem: {
            url: '',
            type: 'custom',
            translations: {}
        },
        init() {
            // Un título por cada idioma activo: al crear un idioma nuevo en el
            // backoffice aparece aquí sin tocar código.
            for (const code of this.locales) {
                this.newItem.translations[code] = '';
            }
        },
        populateFromEntity(ent) {
            for (const code of this.locales) {
                this.newItem.translations[code] = ent['title_' + code] || '';
            }
            this.newItem.url = ent.url || '';
            this.newItem.type = ent.type || 'custom';
        },
        itemTitle(item) {
            const translation = (item.translations || []).find((t) => t.locale === @js($defaultLocale ?? 'es'))
                || (item.translations || [])[0];
            return translation?.title || item.url;
        }
    }" x-init="init()">
        <x-admin.modal
            show="selectedMenu !== null"
            onClose="selectedMenu = null"
            maxWidth="3xl"
            icon="fa-solid fa-link"
        >
            <x-slot:header>
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-[color:var(--admin-accent)]/10 border border-[color:var(--admin-accent)]/30 text-[color:var(--admin-accent)] flex items-center justify-center shrink-0 admin-icon-box">
                        <i class="fa-solid fa-link"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-[color:var(--admin-text)] admin-modal-title flex items-center gap-2">
                            <span>&gt; Enlaces de:</span>
                            <span class="text-[color:var(--admin-accent)] admin-text-accent" x-text="selectedMenu?.name"></span>
                        </h3>
                        <p class="text-[11px] text-[color:var(--admin-muted)] admin-text-muted">{{ __('admin.menus.assigned_location') }} <span class="font-bold" x-text="selectedMenu?.location"></span></p>
                    </div>
                </div>
            </x-slot:header>

            <!-- Existing Items List -->
            <div class="space-y-2">
                <h4 class="text-xs font-bold text-[color:var(--admin-text)] admin-text-secondary uppercase tracking-wider flex items-center gap-2">
                    <i class="fa-solid fa-list-check text-[color:var(--admin-accent)] admin-text-accent"></i>
                    <span>{{ __('admin.menus.current_items') }}</span>
                </h4>
                <div class="space-y-2 max-h-48 overflow-y-auto pr-2">
                    <template x-if="!selectedMenu?.items || selectedMenu.items.length === 0">
                        <p class="text-[color:var(--admin-muted)] border-[color:var(--admin-border)] admin-text-muted text-xs py-3 text-center border border-dashed rounded-lg">{{ __('admin.menus.no_items') }}</p>
                    </template>
                    <template x-for="item in selectedMenu?.items" :key="item.id">
                        <div class="p-3 bg-[color:var(--admin-surface)] border-[color:var(--admin-border)] hover:border-[color:var(--admin-border)] admin-item-card border rounded-lg flex items-center justify-between transition-colors">
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-[color:var(--admin-text)] admin-text-primary text-xs" x-text="itemTitle(item)"></span>
                                <span class="text-[10px] text-[color:var(--admin-accent)] bg-[color:var(--admin-surface)]/50 border-[color:var(--admin-border)] admin-badge-code font-mono px-2 py-0.5 rounded border" x-text="item.url"></span>
                                <span class="text-[9px] text-[color:var(--admin-muted)] bg-[color:var(--admin-surface-raised)] admin-badge-type uppercase font-mono px-1 rounded" x-text="item.type"></span>
                            </div>
                            <form :action="'{{ url(config('cms.admin_path', 'admin') . '/menus/items') }}/' + item.id" method="POST">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-rose-500 hover:text-rose-700 text-xs px-2 py-1 rounded transition-colors">
                                    <i class="fa-solid fa-trash-can mr-1"></i> Eliminar
                                </button>
                            </form>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Add Item Form with Autocomplete Entity Picker -->
            <div class="pt-4 border-t border-[color:var(--admin-border)]/80 space-y-4">
                <div class="flex items-center justify-between">
                    <h4 class="text-xs font-bold text-[color:var(--admin-accent)] admin-text-accent uppercase tracking-wider flex items-center gap-2">
                        <i class="fa-solid fa-plus"></i>
                        <span>{{ __('admin.menus.add_link') }}</span>
                    </h4>
                    <span class="text-[10px] text-[color:var(--admin-muted)] admin-text-muted">{{ __('admin.menus.add_link_hint') }}</span>
                </div>

                <!-- Descriptive Entity Autocomplete Picker -->
                <div class="bg-[color:var(--admin-surface)] border-[color:var(--admin-border)] admin-entity-box p-3 rounded-xl border space-y-1.5">
                    <label class="admin-field-label block text-[10px] text-[color:var(--admin-muted)] admin-text-muted uppercase font-bold flex items-center gap-1.5">
                        <i class="fa-solid fa-wand-magic-sparkles text-amber-500"></i>
                        <span>{{ __('admin.menus.autocomplete') }}</span>
                    </label>
                    <x-admin.entity-picker
                        :items="$availableEntities ?? []"
                        placeholder="{{ __('admin.menus.search_placeholder') }}"
                        onSelect="populateFromEntity($item)"
                    />
                </div>

                <form :action="'{{ url(config('cms.admin_path', 'admin') . '/menus') }}/' + selectedMenu?.id + '/items'" method="POST" class="space-y-3 text-xs">
                    @csrf
                    <!-- Títulos por idioma activo (mismo patrón que páginas, blog y tienda) -->
                    <div class="flex flex-wrap items-center gap-2 border-b border-[color:var(--admin-border)]/80 pb-2">
                        @foreach(($languages ?? collect()) as $language)
                            <button type="button"
                                    @click="itemLocale = @js($language->code)"
                                    :class="itemLocale === @js($language->code) ? 'bg-[color:var(--admin-accent)] text-black font-bold' : 'text-[color:var(--admin-muted)] hover:text-[color:var(--admin-text)]'"
                                    class="px-3 py-1 rounded transition-all flex items-center gap-1.5">
                                <span>{{ $language->flag }}</span>
                                <span>{{ $language->name }} ({{ strtoupper($language->code) }})</span>
                            </button>
                        @endforeach
                    </div>

                    @foreach(($languages ?? collect()) as $language)
                        <div x-show="itemLocale === @js($language->code)" x-cloak>
                            <label class="admin-field-label block text-[10px] text-[color:var(--admin-muted)] admin-text-muted uppercase font-bold mb-1">
                                {{ __('admin.menus.title') }} ({{ strtoupper($language->code) }}) @if($language->code === ($defaultLocale ?? 'es'))*@endif
                            </label>
                            <input type="text"
                                   :name="'translations[' + @js($language->code) + '][title]'"
                                   x-model="newItem.translations[@js($language->code)]"
                                   @if($language->code === ($defaultLocale ?? 'es')) required @endif
                                   placeholder="{{ __('admin.menus.title_placeholder') }}"
                                   class="w-full bg-[color:var(--admin-surface)] border-[color:var(--admin-border)] text-[color:var(--admin-text)] focus:border-[color:var(--admin-accent)] admin-input border rounded px-3 py-1.5">
                        </div>
                    @endforeach

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-end">
                        <div class="sm:col-span-2">
                            <label class="admin-field-label block text-[10px] text-[color:var(--admin-muted)] admin-text-muted uppercase font-bold mb-1">{{ __('admin.menus.url') }} *</label>
                            <input type="text" name="url" x-model="newItem.url" required placeholder="/blog o https://..." class="admin-control w-full bg-[color:var(--admin-surface)] border-[color:var(--admin-border)] text-[color:var(--admin-text)] focus:border-[color:var(--admin-accent)] admin-input border rounded px-3 py-1.5 font-mono">
                        </div>
                        <div>
                            <label class="admin-field-label block text-[10px] text-[color:var(--admin-muted)] admin-text-muted uppercase font-bold mb-1">{{ __('admin.menus.type') }}</label>
                            <select name="type" x-model="newItem.type" class="admin-control w-full bg-[color:var(--admin-surface)] border-[color:var(--admin-border)] text-[color:var(--admin-text)] focus:border-[color:var(--admin-accent)] admin-input border rounded px-3 py-1.5">
                                <option value="custom">{{ __('admin.menus.type_custom') }}</option>
                                <option value="post">{{ __('admin.menus.type_post') }}</option>
                                <option value="page">{{ __('admin.menus.type_page') }}</option>
                                <option value="category">{{ __('admin.menus.type_category') }}</option>
                                <option value="product">{{ __('admin.menus.type_product') }}</option>
                            </select>
                        </div>
                    </div>

                    <x-admin.form-actions :submit="false" class="flex justify-end pt-2">
                        <x-admin.button variant="primary" type="submit" class=" py-2 px-5 text-xs font-bold flex items-center gap-2">
                            <i class="fa-solid fa-plus"></i>
                            <span>{{ __('admin.menus.add_item') }}</span>
                        </x-admin.button>
                    </x-admin.form-actions>
                </form>
            </div>
        </x-admin.modal>
    </div>
</div>
@endsection
