@extends('admin.layout')

@section('title', __('admin.tokens.page_title'))

@section('content')
<div class="admin-page max-w-4xl mx-auto space-y-6 font-mono text-xs" x-data="{ mode: 'personal' }">

    <x-admin.form-header :title="__('admin.tokens.create_button')" :back-route="route('admin.tokens.index')" :back-label="__('admin.tokens.back')" />

    <!-- Mode Selector Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div @click="mode = 'personal'" :class="mode === 'personal' ? 'border-[color:var(--admin-accent)] bg-[color:var(--admin-surface)] ring-1 ring-[color:var(--admin-accent)]' : 'border-[color:var(--admin-border)] bg-[color:var(--admin-surface)] opacity-70 hover:opacity-100'" class="p-5 rounded-xl border cursor-pointer transition-all">
            <div class="flex items-center gap-3">
                <span class="text-2xl text-[color:var(--admin-accent)]"><i class="fa-solid fa-user"></i></span>
                <div>
                    <h3 class="font-bold text-[color:var(--admin-text)] text-sm">{{ __('admin.tokens.pat_card_title') }}</h3>
                    <p class="text-[color:var(--admin-muted)] text-[11px] mt-0.5">{{ __('admin.tokens.pat_card_desc') }}</p>
                </div>
            </div>
        </div>

        <div @click="mode = 'm2m'" :class="mode === 'm2m' ? 'border-[color:var(--admin-accent)] bg-[color:var(--admin-surface)] ring-1 ring-[color:var(--admin-accent)]' : 'border-[color:var(--admin-border)] bg-[color:var(--admin-surface)] opacity-70 hover:opacity-100'" class="p-5 rounded-xl border cursor-pointer transition-all">
            <div class="flex items-center gap-3">
                <span class="text-2xl text-[color:var(--admin-accent)]"><i class="fa-solid fa-robot"></i></span>
                <div>
                    <h3 class="font-bold text-[color:var(--admin-text)] text-sm">{{ __('admin.tokens.m2m_card_title') }}</h3>
                    <p class="text-[color:var(--admin-muted)] text-[11px] mt-0.5">{{ __('admin.tokens.m2m_card_desc') }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Mode 1: Personal Access Token Form -->
    <x-admin.form-card x-show="mode === 'personal'" class="bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] rounded-xl space-y-6">
        <div class="border-b border-[color:var(--admin-border)] pb-4">
            <h2 class="text-base font-bold text-[color:var(--admin-text)] flex items-center gap-2">
                <i class="fa-solid fa-shield-halved text-[color:var(--admin-accent)]"></i>
                <span>{{ __('admin.tokens.pat_form_title') }}</span>
            </h2>
            <p class="text-[color:var(--admin-muted)] text-xs mt-1">{{ __('admin.tokens.pat_form_desc') }}</p>
        </div>

        <form method="POST" action="{{ route('admin.tokens.store-personal') }}" class="space-y-6">
            @csrf
            <input type="hidden" name="subject_type" value="users" />

            <div>
                <label class="admin-field-label block text-[color:var(--admin-text)] font-bold mb-2">{{ __('admin.tokens.token_name_label') }} <span class="text-red-400">*</span></label>
                <input type="text" name="name" required placeholder="{{ __('admin.tokens.token_name_placeholder') }}" class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] rounded-lg px-4 py-2.5 text-[color:var(--admin-text)] text-xs focus:outline-none focus:border-[color:var(--admin-accent)]" />
                <p class="text-[11px] text-[color:var(--admin-muted)] mt-1">{{ __('admin.tokens.token_name_hint') }}</p>
            </div>

            <div>
                <label class="admin-field-label block text-[color:var(--admin-text)] font-bold mb-2">{{ __('admin.tokens.subject_label') }} <span class="text-red-400">*</span></label>
                <select name="subject_id" required class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] rounded-lg px-4 py-2.5 text-[color:var(--admin-text)] text-xs focus:outline-none focus:border-[color:var(--admin-accent)]">
                    <optgroup label="{{ __('admin.tokens.core_users') }}">
                        @foreach($users as $u)
                            <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>
                        @endforeach
                    </optgroup>
                    @foreach($moduleProviders as $provKey => $prov)
                        @if(count($prov['records']) > 0)
                            <optgroup label="{{ __('admin.tokens.module_group', ['name' => $prov['name'], 'key' => $provKey]) }}">
                                @foreach($prov['records'] as $rec)
                                    <option value="{{ $rec->id }}">{{ $rec->name ?? 'ID '.$rec->id }} ({{ $rec->email ?? $provKey }})</option>
                                @endforeach
                            </optgroup>
                        @endif
                    @endforeach
                </select>
                <p class="text-[11px] text-[color:var(--admin-muted)] mt-1">{{ __('admin.tokens.subject_hint') }}</p>
            </div>

            <!-- Scopes Selection -->
            <div>
                <label class="admin-field-label block text-[color:var(--admin-text)] font-bold mb-2">{{ __('admin.tokens.scopes_label') }}</label>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 bg-[color:var(--admin-surface)] p-4 rounded-xl border border-[color:var(--admin-border)] max-h-64 overflow-y-auto">
                    @foreach($availableScopes as $scopeKey => $scopeDesc)
                        <label class="flex items-start gap-2.5 p-2 rounded hover:bg-[color:var(--admin-surface-raised)]/40 cursor-pointer">
                            <input type="checkbox" name="scopes[]" value="{{ $scopeKey }}" class="mt-0.5 rounded bg-[color:var(--admin-surface-raised)] border-[color:var(--admin-border)] text-[color:var(--admin-accent)] focus:ring-[color:var(--admin-accent)]" {{ str_starts_with($scopeKey, 'mcp:') ? 'checked' : '' }} />
                            <div>
                                <span class="text-[color:var(--admin-accent)] font-bold block">{{ $scopeKey }}</span>
                                <span class="text-[color:var(--admin-muted)] text-[10px]">{{ $scopeDesc }}</span>
                            </div>
                        </label>
                    @endforeach
                </div>
            </div>

            <x-admin.form-actions :submit="false" class="flex items-center justify-end gap-3 pt-4 border-t border-[color:var(--admin-border)]">
                <a href="{{ route('admin.tokens.index') }}" class="px-4 py-2 rounded-lg text-[color:var(--admin-muted)] hover:text-[color:var(--admin-text)]">{{ __('admin.actions.cancel') }}</a>
                <button type="submit" class="px-5 py-2.5 bg-[color:var(--admin-accent)] hover:bg-[color:var(--admin-accent-hover)] text-[color:var(--admin-on-accent)] font-bold rounded-lg transition-colors">
                    {{ __('admin.tokens.generate_pat') }} &rarr;
                </button>
            </x-admin.form-actions>
        </form>
    </x-admin.form-card>

    <!-- Mode 2: M2M Client Form -->
    <x-admin.form-card x-show="mode === 'm2m'" class="bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] rounded-xl space-y-6">
        <div class="border-b border-[color:var(--admin-border)] pb-4">
            <h2 class="text-base font-bold text-[color:var(--admin-text)] flex items-center gap-2">
                <i class="fa-solid fa-robot text-[color:var(--admin-accent)]"></i>
                <span>{{ __('admin.tokens.m2m_form_title') }}</span>
            </h2>
            <p class="text-[color:var(--admin-muted)] text-xs mt-1">{!! __('admin.tokens.m2m_form_desc') !!}</p>
        </div>

        <form method="POST" action="{{ route('admin.tokens.store-m2m') }}" class="space-y-6">
            @csrf

            <div>
                <label class="admin-field-label block text-[color:var(--admin-text)] font-bold mb-2">{{ __('admin.tokens.app_name_label') }} <span class="text-red-400">*</span></label>
                <input type="text" name="name" required placeholder="{{ __('admin.tokens.app_name_placeholder') }}" class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] rounded-lg px-4 py-2.5 text-[color:var(--admin-text)] text-xs focus:outline-none focus:border-[color:var(--admin-accent)]" />
            </div>

            <div>
                <label for="service-role" class="admin-field-label block text-[color:var(--admin-text)] font-bold mb-2">Rol de la cuenta de servicio</label>
                <select id="service-role" name="role_id" required class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] rounded-lg px-4 py-2.5 text-[color:var(--admin-text)] text-xs">
                    <option value="">Selecciona sus permisos</option>
                    @foreach($serviceRoles as $serviceRole)
                        <option value="{{ $serviceRole->getKey() }}" @selected(old('role_id') == $serviceRole->getKey() || (!old('role_id') && $serviceRole->slug === 'author'))>{{ $serviceRole->name }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Scopes Selection -->
            <div>
                <label class="admin-field-label block text-[color:var(--admin-text)] font-bold mb-2">{{ __('admin.tokens.m2m_scopes_label') }}</label>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 bg-[color:var(--admin-surface)] p-4 rounded-xl border border-[color:var(--admin-border)] max-h-64 overflow-y-auto">
                    @foreach($availableScopes as $scopeKey => $scopeDesc)
                        <label class="flex items-start gap-2.5 p-2 rounded hover:bg-[color:var(--admin-surface-raised)]/40 cursor-pointer">
                            <input type="checkbox" name="scopes[]" value="{{ $scopeKey }}" class="mt-0.5 rounded bg-[color:var(--admin-surface-raised)] border-[color:var(--admin-border)] text-[color:var(--admin-accent)] focus:ring-[color:var(--admin-accent)]" {{ in_array($scopeKey, ['mcp:access', 'mcp:read', 'mcp:write', 'mcp:destructive', 'mcp:write', 'm2m:service']) ? 'checked' : '' }} />
                            <div>
                                <span class="text-[color:var(--admin-accent)] font-bold block">{{ $scopeKey }}</span>
                                <span class="text-[color:var(--admin-muted)] text-[10px]">{{ $scopeDesc }}</span>
                            </div>
                        </label>
                    @endforeach
                </div>
            </div>

            <!-- M2M Explanation Box -->
            <div class="bg-[color:var(--admin-accent)]/30 border border-[color:var(--admin-accent)]/60 rounded-lg p-4 text-[color:var(--admin-text)] space-y-2">
                <div class="font-bold text-[color:var(--admin-accent)]">{{ __('admin.tokens.m2m_flow_title') }}</div>
                <div class="text-[11px] text-[color:var(--admin-muted)]">
                    {!! __('admin.tokens.m2m_flow_desc') !!}
                </div>
                <pre class="bg-[color:var(--admin-surface)]/60 p-2.5 rounded text-[color:var(--admin-accent)] text-[10px] overflow-x-auto">grant_type=client_credentials&client_id={ID}&client_secret={SECRET}&scope=mcp:access mcp:write</pre>
            </div>

            <x-admin.form-actions :submit="false" class="flex items-center justify-end gap-3 pt-4 border-t border-[color:var(--admin-border)]">
                <a href="{{ route('admin.tokens.index') }}" class="px-4 py-2 rounded-lg text-[color:var(--admin-muted)] hover:text-[color:var(--admin-text)]">{{ __('admin.actions.cancel') }}</a>
                <button type="submit" class="px-5 py-2.5 bg-[color:var(--admin-accent)] hover:bg-[color:var(--admin-accent-hover)] text-[color:var(--admin-on-accent)] font-bold rounded-lg transition-colors">
                    {{ __('admin.tokens.create_m2m') }} &rarr;
                </button>
            </x-admin.form-actions>
        </form>
    </x-admin.form-card>

</div>
@endsection
