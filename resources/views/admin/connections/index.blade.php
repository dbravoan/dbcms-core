@extends('admin.layout')

@section('title', __('admin.page_titles.connections'))

@section('content')
<div class="admin-page space-y-6 max-w-5xl mx-auto pb-16 font-mono text-xs" x-data="externalConnections()">
    <!-- Breadcrumb Header -->
    <div class="flex items-center gap-2 text-[color:var(--admin-muted)] text-xs">
        <a href="{{ route('admin.dashboard') }}" class="hover:text-[color:var(--admin-text)] transition-colors">{{ __('admin.connections.panel') }}</a>
        <span>/</span>
        <span class="text-[color:var(--admin-muted)]">{{ __('admin.connections.system') }}</span>
        <span>/</span>
        <span class="text-[color:var(--admin-accent)] font-bold">{{ __('admin.nav.items.connections') }}</span>
    </div>

    <x-admin.form-header :title="__('admin.connections.title')" :description="__('admin.connections.subtitle')">
        <x-slot:actions>
            <x-admin.status-badge :active="$isConfigured" :active-text="__('admin.connections.badge_active')" :inactive-text="__('admin.connections.badge_inactive')" />
        </x-slot:actions>
    </x-admin.form-header>

    <x-admin.form-card class="px-4 py-3 text-[color:var(--admin-muted)] border border-[color:var(--admin-border)]/80">
        <i class="fa-solid fa-circle-info text-[color:var(--admin-accent)] mr-2"></i>
        {{ $isConfigured ? __('admin.connections.badge_active_desc') : __('admin.connections.badge_inactive_desc') }}
    </x-admin.form-card>

    <!-- Storage Form -->
    <form action="{{ route('admin.connections.update') }}" method="POST" class="space-y-6">
        @csrf
        @method('PUT')

        <x-admin.form-card class="sm:p-7 space-y-6">
            <div>
                <h2 class="text-sm font-bold text-[color:var(--admin-text)] flex items-center gap-2">
                    <span class="text-[color:var(--admin-accent)]">&gt;</span> {{ __('admin.connections.storage_title') }}
                </h2>
                <p class="text-[color:var(--admin-muted)] text-xs mt-1">{{ __('admin.connections.storage_desc') }}</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-4 border-t border-[color:var(--admin-border)]/80">
                <div class="space-y-1.5 md:col-span-2">
                    <label class="admin-field-label font-bold text-[color:var(--admin-text)] text-xs">{{ __('admin.connections.field_provider') }}</label>
                    <select name="provider" x-model="provider"
                            class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] focus:border-[color:var(--admin-accent)] rounded-lg px-3 py-2 text-[color:var(--admin-text)] text-xs outline-none transition-colors">
                        @foreach($providerLabels as $key => $label)
                            <option value="{{ $key }}" @selected(old('provider', $configuration['provider']) === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="text-[11px] text-[color:var(--admin-muted)]">{{ __('admin.connections.hint_provider') }}</p>
                </div>
            </div>
        </x-admin.form-card>

        <!-- Credentials & Location (hidden for local storage) -->
        <x-admin.form-card x-show="provider !== 'local'" x-cloak class="sm:p-7 space-y-6">
            <div>
                <h2 class="text-sm font-bold text-[color:var(--admin-text)] flex items-center gap-2">
                    <span class="text-[color:var(--admin-accent)]">&gt;</span> {{ __('admin.connections.credentials_title') }}
                </h2>
                <p class="text-[color:var(--admin-muted)] text-xs mt-1">{{ __('admin.connections.credentials_desc') }}</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-4 border-t border-[color:var(--admin-border)]/80">
                <div class="space-y-1.5 md:col-span-2">
                    <label class="admin-field-label font-bold text-[color:var(--admin-text)] text-xs">{{ __('admin.connections.field_endpoint') }}</label>
                    <input type="text" name="endpoint" value="{{ old('endpoint', $configuration['endpoint']) }}"
                           class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] focus:border-[color:var(--admin-accent)] rounded-lg px-3 py-2 text-[color:var(--admin-text)] text-xs outline-none transition-colors"
                           placeholder="https://s3.eu-west-par.io.cloud.ovh.net">
                    <p class="text-[11px] text-[color:var(--admin-muted)]">{{ __('admin.connections.hint_endpoint') }}</p>
                </div>

                <div class="space-y-1.5">
                    <label class="admin-field-label font-bold text-[color:var(--admin-text)] text-xs">{{ __('admin.connections.field_region') }}</label>
                    <input type="text" name="region" value="{{ old('region', $configuration['region']) }}"
                           class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] focus:border-[color:var(--admin-accent)] rounded-lg px-3 py-2 text-[color:var(--admin-text)] text-xs outline-none transition-colors"
                           placeholder="eu-west-par">
                    <p class="text-[11px] text-[color:var(--admin-muted)]">{{ __('admin.connections.hint_region') }}</p>
                </div>

                <div class="space-y-1.5">
                    <label class="admin-field-label font-bold text-[color:var(--admin-text)] text-xs">{{ __('admin.connections.field_bucket') }}</label>
                    <input type="text" name="bucket" value="{{ old('bucket', $configuration['bucket']) }}"
                           class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] focus:border-[color:var(--admin-accent)] rounded-lg px-3 py-2 text-[color:var(--admin-text)] text-xs outline-none transition-colors"
                           placeholder="dbcms-media">
                    <p class="text-[11px] text-[color:var(--admin-muted)]">{{ __('admin.connections.hint_bucket') }}</p>
                </div>

                <div class="space-y-1.5">
                    <label class="admin-field-label font-bold text-[color:var(--admin-text)] text-xs">{{ __('admin.connections.field_access_key') }}</label>
                    <input type="text" name="access_key" value="{{ old('access_key', $configuration['access_key']) }}" autocomplete="off"
                           class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] focus:border-[color:var(--admin-accent)] rounded-lg px-3 py-2 text-[color:var(--admin-text)] text-xs outline-none transition-colors">
                    <p class="text-[11px] text-[color:var(--admin-muted)]">{{ __('admin.connections.hint_access_key') }}</p>
                </div>

                <div class="space-y-1.5">
                    <label class="admin-field-label font-bold text-[color:var(--admin-text)] text-xs">{{ __('admin.connections.field_secret_key') }}</label>
                    <input type="password" name="secret_key" value="{{ old('secret_key', $configuration['secret_key']) }}" autocomplete="new-password"
                           class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] focus:border-[color:var(--admin-accent)] rounded-lg px-3 py-2 text-[color:var(--admin-text)] text-xs outline-none transition-colors"
                           placeholder="••••••••">
                    <p class="text-[11px] text-[color:var(--admin-muted)]">{{ __('admin.connections.hint_secret_key') }}</p>
                </div>

                <div class="space-y-1.5">
                    <label class="admin-field-label font-bold text-[color:var(--admin-text)] text-xs">{{ __('admin.connections.field_public_url') }}</label>
                    <input type="text" name="public_url" value="{{ old('public_url', $configuration['public_url']) }}"
                           class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] focus:border-[color:var(--admin-accent)] rounded-lg px-3 py-2 text-[color:var(--admin-text)] text-xs outline-none transition-colors"
                           placeholder="https://cdn.midominio.com">
                    <p class="text-[11px] text-[color:var(--admin-muted)]">{{ __('admin.connections.hint_public_url') }}</p>
                </div>

                <div class="space-y-1.5">
                    <label class="admin-field-label font-bold text-[color:var(--admin-text)] text-xs">{{ __('admin.connections.field_root') }}</label>
                    <input type="text" name="root" value="{{ old('root', $configuration['root']) }}"
                           class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] focus:border-[color:var(--admin-accent)] rounded-lg px-3 py-2 text-[color:var(--admin-text)] text-xs outline-none transition-colors"
                           placeholder="media">
                    <p class="text-[11px] text-[color:var(--admin-muted)]">{{ __('admin.connections.hint_root') }}</p>
                </div>

                <div class="space-y-1.5 md:col-span-2">
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="path_style" value="1" @checked(old('path_style', $configuration['path_style']))
                               class="w-4 h-4 rounded border-[color:var(--admin-border)] bg-[color:var(--admin-surface)] text-[color:var(--admin-accent)] focus:ring-[color:var(--admin-accent)]">
                        <span class="font-bold text-[color:var(--admin-text)] text-xs">{{ __('admin.connections.field_path_style') }}</span>
                    </label>
                    <p class="text-[11px] text-[color:var(--admin-muted)]">{{ __('admin.connections.hint_path_style') }}</p>
                </div>
            </div>
        </x-admin.form-card>

        <!-- Actions -->
        <x-admin.form-card class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <p class="text-[color:var(--admin-muted)] text-[11px]">
                <i class="fa-solid fa-shield-halved mr-1"></i> {{ __('admin.connections.security_note') }}
            </p>
            <div class="flex items-center gap-3 shrink-0">
                <a href="{{ route('admin.dashboard') }}" class="px-3 py-1.5 bg-[color:var(--admin-surface)] hover:bg-[color:var(--admin-surface-raised)] border border-[color:var(--admin-border)] text-[color:var(--admin-text)] hover:text-[color:var(--admin-text)] rounded-lg transition-colors font-bold">
                    {{ __('admin.connections.cancel') }}
                </a>
                <x-admin.button variant="primary" type="submit" class="text-xs flex items-center gap-2">
                    <i class="fa-solid fa-floppy-disk"></i>
                    <span>{{ __('admin.connections.save') }}</span>
                </x-admin.button>
            </div>
        </x-admin.form-card>
    </form>

    <!-- Test Connection -->
    @if($isConfigured)
        <form action="{{ route('admin.connections.test') }}" method="POST" class="admin-card p-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            @csrf
            <div>
                <h2 class="text-sm font-bold text-[color:var(--admin-text)] flex items-center gap-2">
                    <span class="text-[color:var(--admin-accent)]">&gt;</span> {{ __('admin.connections.test_title') }}
                </h2>
                <p class="text-[color:var(--admin-muted)] text-xs mt-1">{{ __('admin.connections.test_desc') }}</p>
            </div>
            <button type="submit" class="px-4 py-2 bg-[color:var(--admin-surface)] hover:bg-[color:var(--admin-surface-raised)] border border-[color:var(--admin-accent)]/40 text-[color:var(--admin-accent)] rounded-lg transition-colors text-xs font-bold shrink-0 flex items-center gap-2">
                <i class="fa-solid fa-plug-circle-check"></i>
                <span>{{ __('admin.connections.test_button') }}</span>
            </button>
        </form>

        <!-- Migrate existing files -->
        <x-admin.form-card class="sm:p-7 space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                <div>
                    <h2 class="text-sm font-bold text-[color:var(--admin-text)] flex items-center gap-2">
                        <span class="text-[color:var(--admin-accent)]">&gt;</span> {{ __('admin.connections.migrate_title') }}
                    </h2>
                    <p class="text-[color:var(--admin-muted)] text-xs mt-1">{{ __('admin.connections.migrate_desc') }}</p>
                </div>
                <span class="shrink-0 px-3 py-1.5 rounded-lg bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] text-[color:var(--admin-text)] font-bold">
                    {{ __('admin.connections.migrate_pending') }}: <span class="text-[color:var(--admin-accent)]">{{ $pendingFiles }}</span>
                </span>
            </div>

            <div class="pt-4 border-t border-[color:var(--admin-border)]/80 space-y-4">
                <div class="flex flex-wrap items-center gap-5">
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" x-model="migrationOptions.rewriteUrls" class="w-4 h-4 rounded border-[color:var(--admin-border)] bg-[color:var(--admin-surface)] text-[color:var(--admin-accent)] focus:ring-[color:var(--admin-accent)]">
                        <span class="font-bold text-[color:var(--admin-text)] text-xs">{{ __('admin.connections.migrate_rewrite') }}</span>
                    </label>
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" x-model="migrationOptions.pruneLocal" class="w-4 h-4 rounded border-[color:var(--admin-border)] bg-[color:var(--admin-surface)] text-[color:var(--admin-accent)] focus:ring-[color:var(--admin-accent)]">
                        <span class="font-bold text-[color:var(--admin-text)] text-xs">{{ __('admin.connections.migrate_prune') }}</span>
                    </label>
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" x-model="migrationOptions.dryRun" class="w-4 h-4 rounded border-[color:var(--admin-border)] bg-[color:var(--admin-surface)] text-[color:var(--admin-accent)] focus:ring-[color:var(--admin-accent)]">
                        <span class="font-bold text-[color:var(--admin-text)] text-xs">{{ __('admin.connections.migrate_dry_run') }}</span>
                    </label>
                </div>

                <p x-show="migrationOptions.pruneLocal" x-cloak class="text-[11px] text-amber-400">
                    <i class="fa-solid fa-triangle-exclamation mr-1"></i> {{ __('admin.connections.migrate_prune_warning') }}
                </p>

                <!-- Progress -->
                <div x-show="migration" x-cloak class="space-y-2">
                    <div class="flex items-center justify-between text-[11px]">
                        <span class="font-bold text-[color:var(--admin-text)]" x-text="migrationLabel()"></span>
                        <span class="text-[color:var(--admin-muted)]" x-text="(migration?.processed ?? 0) + ' / ' + (migration?.total ?? 0)"></span>
                    </div>
                    <div class="w-full h-2 rounded-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] overflow-hidden">
                        <div class="h-full bg-[color:var(--admin-accent)] transition-all" :style="`width: ${migration?.percent ?? 0}%`"></div>
                    </div>
                    <div class="flex flex-wrap gap-4 text-[11px] text-[color:var(--admin-muted)]">
                        <span>Migrados: <span class="text-[color:var(--admin-text)]" x-text="migration?.migrated ?? 0"></span></span>
                        <span>Omitidos: <span class="text-[color:var(--admin-text)]" x-text="migration?.skipped ?? 0"></span></span>
                        <span>Fallidos: <span :class="(migration?.failed ?? 0) > 0 ? 'text-red-400' : 'text-[color:var(--admin-text)]'" x-text="migration?.failed ?? 0"></span></span>
                        <span x-show="!migrationOptions.dryRun">Reescritas: <span class="text-[color:var(--admin-text)]" x-text="migration?.rewritten ?? 0"></span></span>
                    </div>
                    <template x-if="migration?.errors && migration.errors.length">
                        <ul class="mt-1 space-y-0.5 text-[11px] text-amber-400 max-h-32 overflow-y-auto">
                            <template x-for="(err, i) in migration.errors" :key="i">
                                <li x-text="'• ' + err"></li>
                            </template>
                        </ul>
                    </template>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <p class="text-[color:var(--admin-muted)] text-[11px]">
                    <i class="fa-solid fa-circle-info mr-1"></i> {{ __('admin.connections.migrate_hint') }}
                </p>
                <form action="{{ route('admin.connections.migrate') }}" method="POST" class="shrink-0">
                    @csrf
                    <input type="hidden" name="rewrite_urls" :value="migrationOptions.rewriteUrls ? 1 : 0">
                    <input type="hidden" name="prune_local" :value="migrationOptions.pruneLocal ? 1 : 0">
                    <input type="hidden" name="dry_run" :value="migrationOptions.dryRun ? 1 : 0">
                    <button type="submit"
                            :disabled="isMigrating"
                            :class="isMigrating ? 'opacity-50 cursor-not-allowed' : ''"
                            class="px-4 py-2 bg-[color:var(--admin-accent)]/10 hover:bg-[color:var(--admin-accent)] text-[color:var(--admin-accent)] hover:text-black border border-[color:var(--admin-accent)]/40 rounded-lg transition-colors text-xs font-bold flex items-center gap-2">
                        <i class="fa-solid fa-cloud-arrow-up"></i>
                        <span x-text="isMigrating ? @js(__('admin.connections.migrate_running')) : @js(__('admin.connections.migrate_button'))"></span>
                    </button>
                </form>
            </div>
        </x-admin.form-card>
    @endif
</div>

<script nonce="{{ $cspNonce ?? '' }}">
function externalConnections() {
    return {
        provider: @js(old('provider', $configuration['provider'])),
        migration: @js($migrationRun),
        migrationOptions: {
            rewriteUrls: true,
            pruneLocal: false,
            dryRun: false,
        },
        pollTimer: null,

        get isMigrating() {
            return this.migration !== null && ! this.migration.finished;
        },

        migrationLabel() {
            if (! this.migration) return '';
            const map = { pending: 'En cola', running: 'En curso', completed: 'Completada', failed: 'Con errores' };
            return map[this.migration.status] ?? this.migration.status;
        },

        init() {
            if (this.isMigrating) {
                this.startPolling();
            }
        },

        startPolling() {
            this.stopPolling();
            this.pollTimer = setInterval(() => this.poll(), 2500);
        },

        stopPolling() {
            if (this.pollTimer) {
                clearInterval(this.pollTimer);
                this.pollTimer = null;
            }
        },

        async poll() {
            try {
                const response = await fetch('{{ route('admin.connections.migration.status') }}', {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (! response.ok) return;
                const payload = await response.json();
                if (payload.run) {
                    this.migration = payload.run;
                    if (this.migration.finished) this.stopPolling();
                }
            } catch (error) {
                // Keep polling on transient errors.
            }
        },
    };
}
</script>
@endsection
