@extends('admin.layout')

@section('title', __('admin.page_titles.pages'))

@section('content')
<div class="admin-page space-y-6 font-mono text-xs">

    <!-- Unified Datatable Header with Search & Filters -->
    <x-admin.datatable-header
        title="{{ __('admin.listing.pages_title') }}"
        description="{{ __('admin.listing.pages_desc') }}"
        search-placeholder="{{ __('admin.listing.pages_search') }}"
        :create-route="route('admin.pages.create')"
        create-label="+ Crear página"
    >
        <!-- Custom Filter: Status -->
        <select name="is_published" class="admin-control bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] text-[color:var(--admin-text)] focus:border-[color:var(--admin-accent)] rounded-lg px-3 py-2 text-xs">
            <option value="all">{{ __('admin.listing.all_statuses') }}</option>
            <option value="1" {{ request('is_published') === '1' ? 'selected' : '' }}>{{ __('admin.listing.published_f') }}</option>
            <option value="0" {{ request('is_published') === '0' ? 'selected' : '' }}>{{ __('admin.listing.drafts') }}</option>
        </select>
    </x-admin.datatable-header>

    <!-- Pages Table -->
    <x-admin.table
        :empty="$pages->isEmpty()"
        emptyTitle="{{ __('admin.listing.pages_empty_title') }}"
        emptyDescription="{{ __('admin.listing.pages_empty_desc') }}"
        :emptyActionRoute="route('admin.pages.create')"
        emptyActionLabel="+ Crear página"
    >
        <x-slot:header>
            <th class="py-3 px-2 md:hidden w-8"></th>
            <x-admin.sortable-th column="title" label="{{ __('admin.listing.page_title') }}" />
            <th class="py-3 px-4 font-bold uppercase tracking-wider text-[color:var(--admin-muted)] hidden md:table-cell">{{ __('admin.listing.route_url') }}</th>
            <th class="py-3 px-4 font-bold uppercase tracking-wider text-[color:var(--admin-muted)] hidden lg:table-cell">{{ __('admin.listing.blocks') }}</th>
            <th class="py-3 px-4 font-bold uppercase tracking-wider text-[color:var(--admin-muted)] hidden xl:table-cell">{{ __('admin.listing.markets') }}</th>
            <th class="py-3 px-4 font-bold uppercase tracking-wider text-[color:var(--admin-muted)] hidden xl:table-cell">{{ __('admin.listing.languages') }}</th>
            <x-admin.sortable-th column="is_published" label="{{ __('admin.listing.status') }}" class="hidden sm:table-cell" />
            <x-admin.sortable-th column="created_at" label="{{ __('admin.listing.date') }}" class="hidden xl:table-cell" />
            <th class="py-3 px-4 text-right font-bold uppercase tracking-wider text-[color:var(--admin-muted)]">{{ __('admin.listing.actions') }}</th>
        </x-slot:header>

        @foreach($pages as $page)
            @php
                $trans = $page->translations->first();
                $url = $pagePublicUrls[$page->id] ?? null;
            @endphp
            <tr class="hover:bg-[color:var(--admin-surface-raised)]/30 transition-colors">
                <!-- Mobile Chevron Toggle -->
                <td class="py-3.5 px-2 md:hidden text-center w-8">
                    <button type="button"
                            @click="expanded = expanded === $el.closest('tr') ? null : $el.closest('tr')"
                            class="w-6 h-6 rounded-md flex items-center justify-center text-[color:var(--admin-muted)] hover:text-[color:var(--admin-text)] hover:bg-[color:var(--admin-surface-raised)] transition-colors"
                            :title="expanded ? @js(__('admin.listing.hide_details')) : @js(__('admin.listing.show_details'))">
                        <i class="fa-solid text-[10px] transition-transform duration-200"
                           :class="expanded === $el.closest('tr') ? 'fa-chevron-up text-[color:var(--admin-accent)]' : 'fa-chevron-down'"></i>
                    </button>
                </td>

                <td class="py-3.5 px-4 font-bold text-[color:var(--admin-text)] max-w-xs truncate">
                    {{ $trans?->title ?? 'Sin título' }}
                </td>
                <td class="py-3.5 px-4 text-[color:var(--admin-accent)] font-mono hidden md:table-cell">
                    {{ $url ?? '—' }}
                </td>
                <td class="py-3.5 px-4 hidden lg:table-cell">
                    <x-admin.chip>
                        {{ $page->sections->count() }} secciones
                    </x-admin.chip>
                </td>
                <td class="py-3.5 px-4 hidden xl:table-cell">
                    <x-admin.market-chips :markets="$page->markets" />
                </td>
                <td class="py-3.5 px-4 hidden xl:table-cell">
                    <x-admin.locale-chips :locales="$page->translations->pluck('locale')" />
                </td>
                <td class="py-3.5 px-4 hidden sm:table-cell">
                    <x-admin.status-badge :active="$page->is_published" activeText="Publicada" inactiveText="Borrador" />
                </td>
                <td class="py-3.5 px-4 text-[color:var(--admin-muted)] hidden xl:table-cell">{{ $page->created_at->format('Y-m-d') }}</td>
                <td class="py-3.5 px-4 text-right">
                    <div class="inline-flex items-center justify-end gap-1.5">
                        <x-admin.action-button
                            href="{{ route('admin.pages.edit', $page) }}"
                            icon="fa-solid fa-pen-to-square"
                            tooltip="Editar página"
                            variant="primary"
                        />
                        @if($url)
                            <x-admin.action-button
                                href="{{ $url }}"
                                target="_blank"
                                icon="fa-solid fa-arrow-up-right-from-square"
                                tooltip="Ver en sitio"
                                variant="view"
                            />
                        @endif
                        <x-admin.action-button
                            type="button"
                            @click="$dispatch('confirm-delete', {
                                action: '{{ route('admin.pages.destroy', $page) }}',
                                title: 'Eliminar página',
                                message: '¿Confirmas que deseas eliminar esta página del sitio web?'
                            })"
                            icon="fa-solid fa-trash-can"
                            tooltip="Eliminar página"
                            variant="danger"
                        />
                    </div>
                </td>
            </tr>

            <!-- Mobile Accordion Detail Row -->
            <x-admin.table-row-detail>
                <x-admin.table-row-detail-item label="{{ __('admin.listing.route_url') }}">
                    <span class="text-[color:var(--admin-accent)] font-mono">{{ $url ?? '—' }}</span>
                </x-admin.table-row-detail-item>
                <x-admin.table-row-detail-item label="{{ __('admin.listing.modular_blocks') }}">
                    <x-admin.chip>{{ $page->sections->count() }} secciones</x-admin.chip>
                </x-admin.table-row-detail-item>
                <x-admin.table-row-detail-item label="{{ __('admin.listing.markets') }}">
                    <x-admin.market-chips :markets="$page->markets" />
                </x-admin.table-row-detail-item>
                <x-admin.table-row-detail-item label="{{ __('admin.listing.languages') }}">
                    <x-admin.locale-chips :locales="$page->translations->pluck('locale')" />
                </x-admin.table-row-detail-item>
                <x-admin.table-row-detail-item label="{{ __('admin.listing.status') }}">
                    <x-admin.status-badge :active="$page->is_published" activeText="Publicada" inactiveText="Borrador" />
                </x-admin.table-row-detail-item>
                <x-admin.table-row-detail-item label="{{ __('admin.listing.created_at') }}" :value="$page->created_at->format('Y-m-d')" />
            </x-admin.table-row-detail>
        @endforeach

        <x-slot:pagination>
            <x-admin.datatable-pagination :paginator="$pages" />
        </x-slot:pagination>
    </x-admin.table>
</div>
@endsection
