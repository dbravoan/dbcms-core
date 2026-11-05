@extends('admin.layout')

@section('title', __('admin.page_titles.module_settings') . ' ' . $module->name)

@section('content')
<div class="admin-page space-y-6 max-w-4xl mx-auto pb-12 font-mono text-xs">
    <!-- Breadcrumb Header -->
    <div class="flex items-center gap-2 text-[color:var(--admin-muted)] text-xs">
        <a href="{{ route('admin.dashboard') }}" class="hover:text-[color:var(--admin-text)] transition-colors">Panel</a>
        <span>/</span>
        <a href="{{ route('admin.modules.index') }}" class="hover:text-[color:var(--admin-text)] transition-colors">Módulos</a>
        <span>/</span>
        <span class="text-[color:var(--admin-text)] font-bold">{{ $module->name }}</span>
        <span>/</span>
        <span class="text-[color:var(--admin-accent)]">{{ __('admin.modules.settings_title') }}</span>
    </div>

    <!-- Module Identity Card -->
    <x-admin.form-card class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-[color:var(--admin-surface)] border border-[color:var(--admin-accent)]/40 flex items-center justify-center text-xl font-bold text-[color:var(--admin-accent)] shrink-0">
                {{ strtoupper(substr($module->identifier, 0, 1)) }}
            </div>
            <div>
                <div class="flex items-center gap-2 flex-wrap">
                    <x-admin.form-header :title="$module->name" />
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded border border-[color:var(--admin-border)] bg-[color:var(--admin-surface)] text-[color:var(--admin-text)]">v{{ $module->version }}</span>
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded border border-[color:var(--admin-accent)]/30 bg-[color:var(--admin-accent)]/10 text-[color:var(--admin-accent)] uppercase">{{ $module->identifier }}</span>
                </div>
                <p class="text-[color:var(--admin-muted)] text-xs mt-1">{{ $module->description ?: __('admin.modules.default_description', ['app' => config('cms.name')]) }}</p>
            </div>
        </div>

        <div class="shrink-0 flex items-center gap-3">
            @if($configured)
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold bg-[color:var(--admin-accent)]/10 text-[color:var(--admin-accent)] border border-[color:var(--admin-accent)]/30">
                    <span class="w-1.5 h-1.5 rounded-full bg-[color:var(--admin-accent)]"></span>
                    Requisitos configurados
                </span>
            @else
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold bg-amber-500/10 text-amber-400 border border-amber-500/30">
                    <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span>
                    Configuración pendiente
                </span>
            @endif

            <a href="{{ route('admin.modules.index') }}" class="px-3 py-1.5 bg-[color:var(--admin-surface)] hover:bg-[color:var(--admin-surface-raised)] border border-[color:var(--admin-border)] text-[color:var(--admin-text)] hover:text-[color:var(--admin-text)] rounded-lg transition-colors text-xs font-bold">
                &larr; Volver
            </a>
        </div>
    </x-admin.form-card>

    <!-- Settings Form -->
    @if(empty($schema))
        <x-admin.form-card class="p-8 text-center space-y-3">
            <div class="text-2xl text-[color:var(--admin-muted)]"><i class="fa-solid fa-gear"></i></div>
            <h3 class="text-sm font-bold text-[color:var(--admin-text)]">Este módulo no requiere configuración adicional</h3>
            <p class="text-[color:var(--admin-muted)] text-xs max-w-md mx-auto">
                No hay claves de API, credenciales ni parámetros definidos en el manifiesto <code>module.json</code> de este módulo.
            </p>
            <div class="pt-2">
                <a href="{{ route('admin.modules.index') }}" class="admin-btn admin-btn--primary text-xs">
                    Volver a lista de módulos
                </a>
            </div>
        </x-admin.form-card>
    @else
        <form action="{{ route('admin.modules.settings.update', ['identifier' => $module->identifier]) }}" method="POST" class="space-y-6">
            @csrf
            @method('PUT')

            <x-admin.form-card class="sm:p-7 space-y-6 divide-y divide-[color:var(--admin-border)]/80">
                <div class="pb-2">
                    <h2 class="text-sm font-bold text-[color:var(--admin-text)] flex items-center gap-2">
                        <span class="text-[color:var(--admin-accent)]">&gt;</span> Parámetros y Credenciales del Módulo
                    </h2>
                    <p class="text-[color:var(--admin-muted)] text-xs mt-1">Los cambios se aplican de forma inmediata en el runtime y quedan persistidos en el registro del módulo.</p>
                </div>

                @php
                    // Global fields on top; the ones scoped to a language/market
                    // live inside the tabs below (so a language created in the
                    // backoffice gets its own field without touching code).
                    $tabs = [];
                    foreach ($schema as $key => $field) {
                        if (in_array($field['scope'] ?? null, ['locale', 'market'], true)) {
                            $tabs[$field['scope']][$key] = $field;
                        }
                    }
                    $renderField = function (string $key, array $field) use ($settings): string {
                        return view('admin.modules.partials.setting-field', [
                            'key' => $key,
                            'field' => $field,
                            'settings' => $settings,
                        ])->render();
                    };
                @endphp

                @foreach($schema as $key => $field)
                    @if(! in_array($field['scope'] ?? null, ['locale', 'market'], true))
                        {!! $renderField($key, $field) !!}
                    @endif
                @endforeach
            </x-admin.form-card>

            @if(($tabs['locale'] ?? []) !== [])
                <x-admin.form-card class="sm:p-7 space-y-4"
                     x-data="{ activeScope: {{ Illuminate\Support\Js::from(array_key_first($tabs['locale']))->toHtml() }} }"
                     x-init="activeScope = {{ Illuminate\Support\Js::from(array_key_first($tabs['locale']))->toHtml() }}">
                    <div class="pb-2">
                        <h2 class="text-sm font-bold text-[color:var(--admin-text)] flex items-center gap-2">
                            <span class="text-[color:var(--admin-accent)]">&gt;</span> {{ __('admin.modules.locale_settings_title') }}
                        </h2>
                        <p class="text-[color:var(--admin-muted)] text-xs mt-1">{{ __('admin.modules.locale_settings_desc') }}</p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        @foreach($tabs['locale'] as $key => $field)
                            <button type="button"
                                    @click="activeScope = @js($key)"
                                    :class="activeScope === @js($key) ? 'bg-[color:var(--admin-accent)] text-black font-bold' : 'text-[color:var(--admin-muted)] hover:text-[color:var(--admin-text)]'"
                                    class="px-3.5 py-1.5 rounded transition-all flex items-center gap-1.5 text-xs">
                                <span>{{ ($activeLanguages ?? collect())->firstWhere('code', $field['locale'] ?? '')?->flag ?? '🌐' }}</span>
                                <span>{{ ($activeLanguages ?? collect())->firstWhere('code', $field['locale'] ?? '')?->name ?? strtoupper((string) ($field['locale'] ?? '')) }}</span>
                            </button>
                        @endforeach
                    </div>

                    @foreach($tabs['locale'] as $key => $field)
                        <div x-show="activeScope === @js($key)" x-cloak class="pt-2">
                            {!! $renderField($key, $field) !!}
                        </div>
                    @endforeach
                </x-admin.form-card>
            @endif

            @if(($tabs['market'] ?? []) !== [])
                <x-admin.form-card class="sm:p-7 space-y-4"
                     x-data="{ activeScope: {{ Illuminate\Support\Js::from(array_key_first($tabs['market']))->toHtml() }} }"
                     x-init="activeScope = {{ Illuminate\Support\Js::from(array_key_first($tabs['market']))->toHtml() }}">
                    <div class="pb-2">
                        <h2 class="text-sm font-bold text-[color:var(--admin-text)] flex items-center gap-2">
                            <span class="text-[color:var(--admin-accent)]">&gt;</span> {{ __('admin.modules.market_settings_title') }}
                        </h2>
                        <p class="text-[color:var(--admin-muted)] text-xs mt-1">{{ __('admin.modules.market_settings_desc') }}</p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        @foreach($tabs['market'] as $key => $field)
                            <button type="button"
                                    @click="activeScope = @js($key)"
                                    :class="activeScope === @js($key) ? 'bg-[color:var(--admin-accent)] text-black font-bold' : 'text-[color:var(--admin-muted)] hover:text-[color:var(--admin-text)]'"
                                    class="px-3.5 py-1.5 rounded transition-all flex items-center gap-1.5 text-xs">
                                <span>{{ ($activeMarkets ?? collect())->firstWhere('code', $field['market'] ?? '')?->flag ?? '🌐' }}</span>
                                <span>{{ ($activeMarkets ?? collect())->firstWhere('code', $field['market'] ?? '')?->name ?? strtoupper((string) ($field['market'] ?? '')) }}</span>
                            </button>
                        @endforeach
                    </div>

                    @foreach($tabs['market'] as $key => $field)
                        <div x-show="activeScope === @js($key)" x-cloak class="pt-2">
                            {!! $renderField($key, $field) !!}
                        </div>
                    @endforeach
                </x-admin.form-card>
            @endif

            <!-- Provider Context & External Guides -->
            @if($module->identifier === 'meta')
                <div class="p-4 rounded-xl bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] flex items-center justify-between text-xs">
                    <div>
                        <span class="font-bold text-[color:var(--admin-text)]">{{ __('admin.modules.meta_help_title') }}</span>
                        <p class="text-[color:var(--admin-muted)] text-[11px] mt-0.5">{{ __('admin.modules.meta_help_desc') }}</p>
                    </div>
                    <a href="https://business.facebook.com/events_manager2" target="_blank" class="text-[color:var(--admin-accent)] hover:underline shrink-0 text-xs font-bold">
                        Meta Events Manager &nearr;
                    </a>
                </div>
            @elseif($module->identifier === 'analytics')
                <div class="p-4 rounded-xl bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] flex items-center justify-between text-xs">
                    <div>
                        <span class="font-bold text-[color:var(--admin-text)]">¿Dónde obtener tu ID de Medición GA4?</span>
                        <p class="text-[color:var(--admin-muted)] text-[11px] mt-0.5">En Google Analytics &rarr; Administrar &rarr; Flujos de Datos &rarr; Selecciona tu flujo web (formato G-XXXXXXXXXX).</p>
                    </div>
                    <a href="https://analytics.google.com" target="_blank" class="text-[color:var(--admin-accent)] hover:underline shrink-0 text-xs font-bold">
                        Google Analytics &nearr;
                    </a>
                </div>
            @elseif($module->identifier === 'ads')
                <div class="p-4 rounded-xl bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] flex items-center justify-between text-xs">
                    <div>
                        <span class="font-bold text-[color:var(--admin-text)]">¿Dónde obtener tu ID de Editor de AdSense?</span>
                        <p class="text-[color:var(--admin-muted)] text-[11px] mt-0.5">En Google AdSense &rarr; Cuenta &rarr; Información de la cuenta &rarr; ID de editor (formato ca-pub-XXXXXXXXXXXXXXXX).</p>
                    </div>
                    <a href="https://www.google.com/adsense" target="_blank" class="text-[color:var(--admin-accent)] hover:underline shrink-0 text-xs font-bold">
                        Google AdSense &nearr;
                    </a>
                </div>
            @endif

            <!-- Form Action Footer -->
            <div class="flex items-center justify-between pt-2">
                <a href="{{ route('admin.modules.index') }}" class="px-4 py-2 bg-[color:var(--admin-surface)] hover:bg-[color:var(--admin-surface-raised)] border border-[color:var(--admin-border)] text-[color:var(--admin-text)] hover:text-[color:var(--admin-text)] rounded-lg transition-colors font-bold text-xs">
                    Cancelar
                </a>

                <x-admin.button variant="primary" type="submit" class="text-xs py-2.5 px-6">
                    Guardar Configuración
                </x-admin.button>
            </div>
        </form>
    @endif
    @if($module->identifier === 'mcp')
        @include('mcp::admin.guide')
    @endif
</div>
@endsection
