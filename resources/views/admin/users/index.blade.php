@extends('admin.layout')

@section('title', __('admin.page_titles.users'))

@section('content')
<div class="space-y-6 font-mono text-xs" x-data="{ showCreateModal: false }">

    <!-- Unified Datatable Header with Search & Filter -->
    <x-admin.datatable-header
        title="{{ __('admin.users.title') }}"
        description="Administra cuentas de usuario, asignación de roles y permisos del Backoffice."
        search-placeholder="{{ __('admin.users.search_placeholder') }}"
        create-modal-event="open-create-user-modal"
        create-label="+ Nuevo Usuario"
    >
        <!-- Custom Filter: Role Dropdown -->
        <select name="role" class="bg-[#1A1A1A] border border-gray-700 focus:border-[#00FF99] rounded-lg px-3 py-2 text-white text-xs">
            <option value="all">{{ __('admin.users.all_roles') }}</option>
            @foreach($roles as $r)
                <option value="{{ $r->slug }}" {{ request('role') === $r->slug ? 'selected' : '' }}>
                    Rol: {{ $r->name }}
                </option>
            @endforeach
        </select>
    </x-admin.datatable-header>



    <!-- Users Table -->
    <x-admin.table
        :empty="$users->isEmpty()"
        emptyTitle="No se encontraron usuarios"
        emptyDescription="No hay usuarios que coincidan con los filtros aplicados o aún no has creado ninguno."
        emptyActionEvent="open-create-user-modal"
        emptyActionLabel="+ Nuevo Usuario"
    >
        <x-slot:header>
            <th class="py-3 px-2 md:hidden w-8"></th>
            <x-admin.sortable-th column="name" label="{{ __('admin.users.user_column') }}" />
            <x-admin.sortable-th column="email" label="{{ __('admin.users.email_label') }}" class="hidden sm:table-cell" />
            <th class="py-3 px-4 font-bold uppercase tracking-wider text-gray-400 hidden md:table-cell">{{ __('admin.users.role_label') }}</th>
            <x-admin.sortable-th column="created_at" label="{{ __('admin.users.created_label') }}" class="hidden lg:table-cell" />
            <th class="py-3 px-4 text-right font-bold uppercase tracking-wider text-gray-400">{{ __('admin.common.actions') }}</th>
        </x-slot:header>

        @foreach($users as $u)
            <tr class="hover:bg-gray-800/30 transition-colors">
                <td class="py-3.5 px-2 md:hidden text-center w-8">
                    <button type="button" @click="expanded = expanded === $el.closest('tr') ? null : $el.closest('tr')" class="text-gray-400 hover:text-white p-1 focus:outline-none" aria-label="Ver detalles">
                        <i class="fa-solid text-[10px]" :class="expanded === $el.closest('tr') ? 'fa-chevron-up text-[#00FF99]' : 'fa-chevron-down'"></i>
                    </button>
                </td>
                <td class="py-3.5 px-4 font-bold text-white flex items-center gap-2">
                    <div class="w-7 h-7 rounded-full bg-gray-800 border border-gray-700 flex items-center justify-center text-[10px] text-[#00FF99] font-bold">
                        {{ strtoupper(substr($u->name, 0, 1)) }}
                    </div>
                    <div>
                        <div>{{ $u->name }}</div>
                        <div class="text-[11px] text-gray-400 font-normal sm:hidden">{{ $u->email }}</div>
                    </div>
                </td>
                <td class="py-3.5 px-4 text-gray-300 hidden sm:table-cell">{{ $u->email }}</td>
                <td class="py-3.5 px-4 hidden md:table-cell">
                    @if(app()->isProduction() && config('cms.lock_roles_in_production', true))
                        <x-admin.chip variant="market" icon="fa-solid fa-lock">
                            {{ $u->roles->first()?->name ?? 'Sin Rol' }}
                        </x-admin.chip>
                    @else
                        <form method="POST" action="{{ route('admin.users.update-role', $u) }}" class="inline-flex items-center gap-2">
                            @csrf
                            <select name="role_id" data-auto-submit class="bg-[#1A1A1A] border border-gray-700 text-gray-300 rounded px-2 py-1 text-xs focus:border-[#00FF99]">
                                @foreach($roles as $r)
                                    <option value="{{ $r->id }}" {{ $u->hasRole($r->slug) ? 'selected' : '' }}>
                                        {{ $r->name }}
                                    </option>
                                @endforeach
                            </select>
                        </form>
                    @endif
                </td>
                <td class="py-3.5 px-4 text-gray-400 hidden lg:table-cell">{{ $u->created_at->format('Y-m-d H:i') }}</td>
                <td class="py-3.5 px-4 text-right whitespace-nowrap">
                    <div class="inline-flex items-center justify-end gap-1.5">
                        @if(auth()->id() !== $u->id)
                            <x-admin.action-button
                                icon="fa-solid fa-trash-can"
                                tooltip="Eliminar Usuario"
                                variant="danger"
                                @click="$dispatch('confirm-delete', {
                                    action: '{{ route('admin.users.destroy', $u) }}',
                                    title: {{ \Illuminate\Support\Js::from(__('admin.users.delete_title')) }},
                                    message: {{ \Illuminate\Support\Js::from(__('admin.users.delete_message', ['name' => $u->name, 'email' => $u->email])) }}
                                })"
                            />
                        @else
                            <span class="text-gray-600 text-[10px] italic px-2 py-1 bg-gray-900/50 rounded border border-gray-800">{{ __('admin.users.current_account') }}</span>
                        @endif
                    </div>
                </td>
            </tr>

            <x-admin.table-row-detail colSpan="6">
                <x-admin.table-row-detail-item label="{{ __('admin.users.email_label') }}">
                    <span class="text-gray-300">{{ $u->email }}</span>
                </x-admin.table-row-detail-item>
                <x-admin.table-row-detail-item label="{{ __('admin.users.role_label') }}">
                    @if(app()->isProduction() && config('cms.lock_roles_in_production', true))
                        <x-admin.chip variant="market" icon="fa-solid fa-lock">
                            {{ $u->roles->first()?->name ?? 'Sin Rol' }}
                        </x-admin.chip>
                    @else
                        <form method="POST" action="{{ route('admin.users.update-role', $u) }}" class="inline-flex items-center gap-2">
                            @csrf
                            <select name="role_id" data-auto-submit class="bg-[#1A1A1A] border border-gray-700 text-gray-300 rounded px-2 py-1 text-xs focus:border-[#00FF99]">
                                @foreach($roles as $r)
                                    <option value="{{ $r->id }}" {{ $u->hasRole($r->slug) ? 'selected' : '' }}>
                                        {{ $r->name }}
                                    </option>
                                @endforeach
                            </select>
                        </form>
                    @endif
                </x-admin.table-row-detail-item>
                <x-admin.table-row-detail-item label="Fecha Registro">
                    <span class="text-gray-400">{{ $u->created_at->format('Y-m-d H:i') }}</span>
                </x-admin.table-row-detail-item>
            </x-admin.table-row-detail>
        @endforeach

        <x-slot:pagination>
            <x-admin.datatable-pagination :paginator="$users" />
        </x-slot:pagination>
    </x-admin.table>

    <!-- Create User Modal -->
    <div x-data="{ open: false }" @open-create-user-modal.window="open = true">
        <x-admin.modal
            show="open"
            title="{{ __('admin.users.create_title') }}"
            subtitle="{{ __('admin.users.create_subtitle') }}"
            icon="fa-solid fa-user-plus"
            maxWidth="md"
        >
            <form method="POST" action="{{ route('admin.users.store') }}" class="space-y-4 text-xs">
                @csrf
                <x-admin.input
                    name="name"
                    label="{{ __('admin.users.name_label') }}"
                    placeholder="{{ __('admin.users.name_placeholder') }}"
                    required
                />
                <x-admin.input
                    type="email"
                    name="email"
                    label="{{ __('admin.users.email_label') }}"
                    placeholder="{{ __('admin.users.email_placeholder') }}"
                    required
                />
                <x-admin.input
                    type="password"
                    name="password"
                    label="{{ __('admin.users.password_label') }}"
                    placeholder="••••••••"
                    required
                />
                <x-admin.select
                    name="role_id"
                    label="{{ __('admin.users.role_label') }}"
                    required
                >
                    @foreach($roles as $r)
                        <option value="{{ $r->id }}">{{ $r->name }} ({{ $r->slug }})</option>
                    @endforeach
                </x-admin.select>

                <div class="flex justify-end gap-3 pt-4 border-t border-gray-800">
                    <button type="button" @click="open = false" class="px-4 py-2 bg-gray-800 text-gray-400 hover:text-white rounded-lg">{{ __('admin.actions.cancel') }}</button>
                    <button type="submit" class="btn-neon">{{ __('admin.users.create_button') }}</button>
                </div>
            </form>
        </x-admin.modal>
    </div>
</div>
@endsection
