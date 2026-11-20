@extends('admin.layout')

@section('title', __('admin.page_titles.tokens'))

@section('content')
<div class="admin-page space-y-6 font-mono text-xs" x-data="{ activeTab: 'personal' }">

    <x-admin.form-header :title="__('admin.tokens.title')" description="Administra los tokens de acceso de usuarios y clientes de agentes y protocolos MCP.">
        <x-slot:actions><x-admin.button :href="route('admin.tokens.create')" variant="primary" icon="fa-solid fa-plus">{{ __('admin.tokens.create_button') }}</x-admin.button></x-slot:actions>
    </x-admin.form-header>

    <!-- Alert for Newly Issued Token (Single-View Secret) -->
    @if(session('issued_token'))
    <div class="bg-[color:var(--admin-surface)]/90 border-2 border-[color:var(--admin-accent)] rounded-xl p-5 text-[color:var(--admin-text)] shadow-2xl space-y-3" x-data="{ copied: false }">
        <div class="flex items-center gap-2 text-[color:var(--admin-accent)] font-bold text-sm">
            <i class="fa-solid fa-shield-halved"></i>
            <span>{{ __('admin.tokens.pat_success_title') }}</span>
        </div>
        <p class="text-[color:var(--admin-text)] text-xs">
            {!! __('admin.tokens.pat_success_desc') !!}
        </p>
        <div class="flex items-center gap-2 bg-[color:var(--admin-surface)] p-3 rounded-lg border border-[color:var(--admin-border)]">
            <input type="text" readonly id="plainTokenInput" value="{{ session('issued_token')['token'] }}" class="w-full bg-transparent text-[color:var(--admin-accent)] text-xs font-mono select-all focus:outline-none" />
            <button type="button" @click="navigator.clipboard.writeText('{{ session('issued_token')['token'] }}'); copied = true; setTimeout(() => copied = false, 3000)" class="px-3 py-1 bg-[color:var(--admin-surface)] hover:bg-[color:var(--admin-surface-raised)] border border-[color:var(--admin-accent)]/40 text-[color:var(--admin-accent)] rounded font-bold transition-colors">
                <span x-text="copied ? @js(__('admin.tokens.copied')) : @js(__('admin.tokens.copy'))"></span>
            </button>
        </div>
    </div>
    @endif

    <!-- Alert for Newly Issued M2M Client -->
    @if(session('issued_m2m_client'))
    <div class="bg-[color:var(--admin-surface)]/90 border-2 border-[color:var(--admin-accent)] rounded-xl p-5 text-[color:var(--admin-text)] shadow-2xl space-y-3" x-data="{ copiedSecret: false }">
        <div class="flex items-center gap-2 text-[color:var(--admin-accent)] font-bold text-sm">
            <i class="fa-solid fa-robot"></i>
            <span>{{ __('admin.tokens.m2m_success_title') }}</span>
        </div>
        <p class="text-[color:var(--admin-text)] text-xs">
            {!! __('admin.tokens.m2m_success_desc') !!}
        </p>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div class="bg-[color:var(--admin-surface)] p-3 rounded-lg border border-[color:var(--admin-border)]">
                <span class="text-[color:var(--admin-muted)] text-[10px] uppercase block">{{ __('admin.tokens.client_id') }}</span>
                <span class="text-[color:var(--admin-text)] font-mono text-xs select-all">{{ session('issued_m2m_client')['client_id'] }}</span>
            </div>
            <div class="bg-[color:var(--admin-surface)] p-3 rounded-lg border border-[color:var(--admin-border)] flex items-center justify-between">
                <div>
                    <span class="text-[color:var(--admin-muted)] text-[10px] uppercase block">{{ __('admin.tokens.client_secret') }}</span>
                    <span class="text-[color:var(--admin-accent)] font-mono text-xs select-all">{{ session('issued_m2m_client')['client_secret'] }}</span>
                </div>
                <button type="button" @click="navigator.clipboard.writeText('{{ session('issued_m2m_client')['client_secret'] }}'); copiedSecret = true; setTimeout(() => copiedSecret = false, 3000)" class="px-2 py-1 bg-[color:var(--admin-surface)] border border-[color:var(--admin-accent)]/40 text-[color:var(--admin-accent)] rounded text-[10px] font-bold">
                    <span x-text="copiedSecret ? 'Copiado' : 'Copiar'"></span>
                </button>
            </div>
        </div>
    </div>
    @endif

    <!-- Tab Navigation -->
    <div class="flex items-center gap-2 border-b border-[color:var(--admin-border)] pb-2">
        <button type="button" @click="activeTab = 'personal'" :class="activeTab === 'personal' ? 'bg-[color:var(--admin-surface-raised)] text-[color:var(--admin-accent)] border-[color:var(--admin-accent)]' : 'text-[color:var(--admin-muted)] hover:text-[color:var(--admin-text)] border-transparent'" class="px-4 py-2 rounded-lg border text-xs font-bold transition-colors flex items-center gap-2">
            <i class="fa-solid fa-user"></i>
            <span>{{ __('admin.tokens.pat_tab') }} ({{ $tokens->total() }})</span>
        </button>
        <button type="button" @click="activeTab = 'm2m'" :class="activeTab === 'm2m' ? 'bg-[color:var(--admin-surface-raised)] text-[color:var(--admin-accent)] border-[color:var(--admin-accent)]' : 'text-[color:var(--admin-muted)] hover:text-[color:var(--admin-text)] border-transparent'" class="px-4 py-2 rounded-lg border text-xs font-bold transition-colors flex items-center gap-2">
            <i class="fa-solid fa-robot"></i>
            <span>{{ __('admin.tokens.m2m_tab') }} ({{ $clients->total() }})</span>
        </button>
    </div>

    <!-- Tab 1: Personal Access Tokens Table -->
    <div x-show="activeTab === 'personal'">
        <x-admin.table
            :empty="$tokens->isEmpty()"
            emptyTitle="No hay Personal Access Tokens"
            emptyDescription="No hay Personal Access Tokens registrados actualmente."
            :emptyActionRoute="route('admin.tokens.create')"
            emptyActionLabel="+ Crear Token"
        >
            <x-slot:header>
                <th class="py-3 px-2 md:hidden w-8"></th>
                <th class="py-3 px-4 uppercase font-bold text-[11px]">{{ __('admin.tokens.col_token_name') }}</th>
                <th class="py-3 px-4 uppercase font-bold text-[11px] hidden sm:table-cell">{{ __('admin.tokens.col_subject') }}</th>
                <th class="py-3 px-4 uppercase font-bold text-[11px] hidden md:table-cell">{{ __('admin.tokens.col_scopes') }}</th>
                <th class="py-3 px-4 uppercase font-bold text-[11px] hidden lg:table-cell">{{ __('admin.tokens.col_created') }}</th>
                <th class="py-3 px-4 uppercase font-bold text-[11px] hidden sm:table-cell">{{ __('admin.tokens.col_status') }}</th>
                <th class="py-3 px-4 text-right uppercase font-bold text-[11px]">{{ __('admin.tokens.col_actions') }}</th>
            </x-slot:header>

            @foreach($tokens as $t)
                <tr class="hover:bg-[color:var(--admin-surface-raised)]/30 transition-colors">
                    <td class="py-3 px-2 md:hidden text-center w-8">
                        <button type="button" @click="expanded = expanded === $el.closest('tr') ? null : $el.closest('tr')" class="text-[color:var(--admin-muted)] hover:text-[color:var(--admin-text)] p-1 focus:outline-none" aria-label="Ver detalles">
                            <i class="fa-solid text-[10px]" :class="expanded === $el.closest('tr') ? 'fa-chevron-up text-[color:var(--admin-accent)]' : 'fa-chevron-down'"></i>
                        </button>
                    </td>
                    <td class="py-3 px-4 font-bold text-[color:var(--admin-text)]">
                        {{ $t->name }}
                        <div class="text-[10px] text-[color:var(--admin-muted)] font-mono">ID: {{ substr($t->id, 0, 16) }}...</div>
                    </td>
                    <td class="py-3 px-4 hidden sm:table-cell">
                        @if($t->user_id && isset($users[$t->user_id]))
                            <span class="px-2 py-0.5 rounded bg-blue-500/10 border border-blue-500/30 text-blue-400 text-[10px] inline-flex items-center gap-1">
                                <i class="fa-solid fa-user text-[10px]"></i> {{ $users[$t->user_id]->name }}
                            </span>
                        @elseif($t->user_id)
                            <span class="px-2 py-0.5 rounded bg-green-500/10 border border-green-500/30 text-green-400 text-[10px] inline-flex items-center gap-1">
                                <i class="fa-solid fa-bag-shopping text-[10px]"></i> Sujeto #{{ $t->user_id }}
                            </span>
                        @else
                            <span class="px-2 py-0.5 rounded bg-[color:var(--admin-accent)]/10 border border-[color:var(--admin-accent)]/30 text-[color:var(--admin-accent)] text-[10px] inline-flex items-center gap-1">
                                <i class="fa-solid fa-robot text-[10px]"></i> Machine-to-Machine
                            </span>
                        @endif
                    </td>
                    <td class="py-3 px-4 hidden md:table-cell">
                        <div class="flex flex-wrap gap-1">
                            @forelse($t->scopes as $s)
                                <x-admin.chip>{{ $s }}</x-admin.chip>
                            @empty
                                <span class="text-[color:var(--admin-muted)] text-[10px]">{{ __('admin.tokens.none') }}</span>
                            @endforelse
                        </div>
                    </td>
                    <td class="py-3 px-4 text-[color:var(--admin-muted)] text-[11px] hidden lg:table-cell">
                        {{ $t->created_at?->format('Y-m-d H:i') ?? '-' }}
                    </td>
                    <td class="py-3 px-4 hidden sm:table-cell">
                        <x-admin.status-badge :active="! $t->revoked" activeText="Activo" inactiveText="Revocado" />
                    </td>
                    <td class="py-3 px-4 text-right whitespace-nowrap">
                        <div class="inline-flex items-center justify-end gap-1.5">
                            @if(! $t->revoked)
                                <x-admin.action-button
                                    icon="fa-solid fa-ban"
                                    tooltip="Revocar Token"
                                    variant="danger"
                                    @click="$dispatch('confirm-delete', {
                                        action: '{{ route('admin.tokens.revoke', $t->id) }}',
                                        title: 'Revocar Token',
                                        message: '¿Seguro que deseas revocar este token de acceso de inmediato?'
                                    })"
                                />
                            @else
                                <span class="text-[color:var(--admin-muted)] text-[11px]">-</span>
                            @endif
                        </div>
                    </td>
                </tr>

                <x-admin.table-row-detail colSpan="7">
                    <x-admin.table-row-detail-item label="{{ __('admin.tokens.col_subject') }}">
                        @if($t->user_id && isset($users[$t->user_id]))
                            <span class="text-blue-400 font-mono">{{ $users[$t->user_id]->name }} ({{ $users[$t->user_id]->email }})</span>
                        @elseif($t->user_id)
                            <span class="text-green-400 font-mono">{{ __('admin.tokens.subject_module', ['id' => $t->user_id]) }}</span>
                        @else
                            <span class="text-[color:var(--admin-accent)] font-mono">{{ __('admin.tokens.m2m') }}</span>
                        @endif
                    </x-admin.table-row-detail-item>
                    <x-admin.table-row-detail-item label="Scopes Concedidos">
                        <div class="flex flex-wrap gap-1">
                            @forelse($t->scopes as $s)
                                <x-admin.chip>{{ $s }}</x-admin.chip>
                            @empty
                                <span class="text-[color:var(--admin-muted)] text-[10px]">{{ __('admin.tokens.none') }}</span>
                            @endforelse
                        </div>
                    </x-admin.table-row-detail-item>
                    <x-admin.table-row-detail-item label="Estado">
                        <x-admin.status-badge :active="! $t->revoked" activeText="Activo" inactiveText="Revocado" />
                    </x-admin.table-row-detail-item>
                    <x-admin.table-row-detail-item label="Fecha de creación">
                        <span class="text-[color:var(--admin-muted)]">{{ $t->created_at?->format('Y-m-d H:i') ?? '-' }}</span>
                    </x-admin.table-row-detail-item>
                </x-admin.table-row-detail>
            @endforeach

            <x-slot:pagination>
                {{ $tokens->links() }}
            </x-slot:pagination>
        </x-admin.table>
    </div>

    <!-- Tab 2: M2M Clients Table -->
    <div x-show="activeTab === 'm2m'">
        <x-admin.table
            :empty="$clients->isEmpty()"
            emptyTitle="No hay clientes M2M"
            emptyDescription="No hay clientes M2M registrados actualmente."
            :emptyActionRoute="route('admin.tokens.create')"
            emptyActionLabel="+ Crear Cliente"
        >
            <x-slot:header>
                <th class="py-3 px-2 md:hidden w-8"></th>
                <th class="py-3 px-4 uppercase font-bold text-[11px]">{{ __('admin.tokens.col_app_name') }}</th>
                <th class="py-3 px-4 uppercase font-bold text-[11px] hidden sm:table-cell">{{ __('admin.tokens.col_client_id') }}</th>
                <th class="py-3 px-4 uppercase font-bold text-[11px] hidden md:table-cell">{{ __('admin.tokens.col_auth_provider') }}</th>
                <th class="py-3 px-4 uppercase font-bold text-[11px] hidden lg:table-cell">{{ __('admin.tokens.col_created') }}</th>
                <th class="py-3 px-4 uppercase font-bold text-[11px] hidden sm:table-cell">{{ __('admin.tokens.col_status') }}</th>
                <th class="py-3 px-4 text-right uppercase font-bold text-[11px]">{{ __('admin.tokens.col_actions') }}</th>
            </x-slot:header>

            @foreach($clients as $c)
                <tr class="hover:bg-[color:var(--admin-surface-raised)]/30 transition-colors">
                    <td class="py-3.5 px-2 md:hidden text-center w-8">
                        <button type="button" @click="expanded = expanded === $el.closest('tr') ? null : $el.closest('tr')" class="text-[color:var(--admin-muted)] hover:text-[color:var(--admin-text)] p-1 focus:outline-none" aria-label="Ver detalles">
                            <i class="fa-solid text-[10px]" :class="expanded === $el.closest('tr') ? 'fa-chevron-up text-[color:var(--admin-accent)]' : 'fa-chevron-down'"></i>
                        </button>
                    </td>
                    <td class="py-3 px-4 font-bold text-[color:var(--admin-text)]">
                        {{ $c->name }}
                        <div class="sm:hidden font-mono text-[color:var(--admin-accent)] text-[10px] mt-0.5 select-all">{{ $c->id }}</div>
                    </td>
                    <td class="py-3 px-4 font-mono text-[color:var(--admin-accent)] text-[11px] select-all hidden sm:table-cell">
                        {{ $c->id }}
                    </td>
                    <td class="py-3 px-4 text-[color:var(--admin-muted)] font-mono text-[11px] hidden md:table-cell">
                        {{ $c->provider ?? 'users' }}
                    </td>
                    <td class="py-3 px-4 text-[color:var(--admin-muted)] text-[11px] hidden lg:table-cell">
                        {{ $c->created_at?->format('Y-m-d H:i') ?? '-' }}
                    </td>
                    <td class="py-3 px-4 hidden sm:table-cell">
                        <x-admin.status-badge :active="! $c->revoked" activeText="Activo" inactiveText="Revocado" />
                    </td>
                    <td class="py-3 px-4 text-right whitespace-nowrap">
                        <div class="inline-flex items-center justify-end gap-1.5">
                            @if(! $c->revoked)
                                <x-admin.action-button
                                    icon="fa-solid fa-ban"
                                    tooltip="Revocar Cliente"
                                    variant="danger"
                                    @click="$dispatch('confirm-delete', {
                                        action: '{{ route('admin.tokens.revoke-client', $c->id) }}',
                                        title: 'Revocar Cliente OAuth2 M2M',
                                        message: '¿Seguro que deseas revocar este cliente OAuth2 M2M?'
                                    })"
                                />
                            @else
                                <span class="text-[color:var(--admin-muted)] text-[11px]">-</span>
                            @endif
                        </div>
                    </td>
                </tr>

                <x-admin.table-row-detail colSpan="7">
                    <x-admin.table-row-detail-item label="Client ID (OAuth2)">
                        <span class="font-mono text-[color:var(--admin-accent)] select-all">{{ $c->id }}</span>
                    </x-admin.table-row-detail-item>
                    <x-admin.table-row-detail-item label="Proveedor Auth">
                        <span class="font-mono text-[color:var(--admin-text)]">{{ $c->provider ?? 'users' }}</span>
                    </x-admin.table-row-detail-item>
                    <x-admin.table-row-detail-item label="Estado">
                        <x-admin.status-badge :active="! $c->revoked" activeText="Activo" inactiveText="Revocado" />
                    </x-admin.table-row-detail-item>
                    <x-admin.table-row-detail-item label="Fecha de creación">
                        <span class="text-[color:var(--admin-muted)]">{{ $c->created_at?->format('Y-m-d H:i') ?? '-' }}</span>
                    </x-admin.table-row-detail-item>
                </x-admin.table-row-detail>
            @endforeach

            <x-slot:pagination>
                {{ $clients->links() }}
            </x-slot:pagination>
        </x-admin.table>
    </div>

</div>
@endsection
