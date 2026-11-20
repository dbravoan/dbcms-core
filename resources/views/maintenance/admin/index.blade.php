@extends('admin.layout')

@section('title', __('admin.page_titles.maintenance'))

@section('content')
<div class="admin-page space-y-6 max-w-6xl mx-auto pb-16 font-mono text-xs" x-data="{
    mode: '{{ $settings['mode'] ?? 'default' }}',
    previewDevice: 'desktop'
}">
    <!-- Breadcrumb Header -->
    <div class="flex items-center justify-between gap-4 flex-wrap">
        <div class="flex items-center gap-2 text-[color:var(--admin-muted)] text-xs">
            <a href="{{ route('admin.dashboard') }}" class="hover:text-[color:var(--admin-text)] transition-colors">{{ __('admin.maintenance.panel') }}</a>
            <span>/</span>
            <a href="{{ route('admin.settings.index') }}" class="hover:text-[color:var(--admin-text)] transition-colors">{{ __('admin.nav.items.settings') }}</a>
            <span>/</span>
            <span class="text-[color:var(--admin-accent)] font-bold">{{ __('admin.maintenance.title') }}</span>
        </div>

        <div class="flex items-center gap-3">
            <x-admin.button :href="route('admin.maintenance.preview')" target="_blank" rel="noopener" variant="ghost" size="sm">
                <i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i>
                <span>{{ __('admin.maintenance.preview') }}</span>
            </x-admin.button>
            <form action="{{ route('admin.maintenance.toggle') }}" method="POST" class="inline">
                @csrf
                <x-admin.button :variant="$isActive ? 'warning' : 'primary'" size="sm" type="submit">
                    <span class="w-2 h-2 rounded-full {{ $isActive ? 'bg-red-400 animate-ping' : 'bg-[color:var(--admin-accent)]' }}"></span>
                    <span>{{ $isActive ? 'Desactivar Mantenimiento' : 'Activar Mantenimiento' }}</span>
                </x-admin.button>
            </form>
        </div>
    </div>

    <!-- Status Hero Card -->
    <x-admin.form-card class="flex flex-col md:flex-row md:items-center justify-between gap-6 border-l-4 {{ $isActive ? 'border-l-amber-500 bg-amber-500/5' : 'border-l-[#00FF99] bg-[color:var(--admin-accent)]/5' }}">
        <div class="space-y-2">
            <div class="flex items-center gap-3 flex-wrap">
                <div class="w-10 h-10 rounded-xl bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] flex items-center justify-center text-lg {{ $isActive ? 'text-amber-400' : 'text-[color:var(--admin-accent)]' }}">
                    <i class="fa-solid fa-screwdriver-wrench"></i>
                </div>
                <div>
                    <div class="flex items-center gap-2.5">
                        <x-admin.form-header :title="__('admin.maintenance.status_title')" />
                        @if($isActive)
                            <span class="px-3 py-0.5 rounded-full text-[11px] font-bold bg-amber-500/20 text-amber-300 border border-amber-500/50 flex items-center gap-1.5 animate-pulse">
                                <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span>
                                MODO MANTENIMIENTO ACTIVO (HTTP 503)
                            </span>
                        @else
                            <span class="px-3 py-0.5 rounded-full text-[11px] font-bold bg-[color:var(--admin-accent)]/20 text-[color:var(--admin-accent)] border border-[color:var(--admin-accent)]/50 flex items-center gap-1.5">
                                <span class="w-1.5 h-1.5 rounded-full bg-[color:var(--admin-accent)]"></span>
                                PORTAL PÚBLICO OPERATIVO (HTTP 200)
                            </span>
                        @endif
                    </div>
                    <p class="text-[color:var(--admin-muted)] text-xs mt-1">
                        @if($isActive)
                            Los visitantes externos están recibiendo la pantalla de mantenimiento con código HTTP 503 y Retry-After: <strong class="text-[color:var(--admin-text)]">{{ $retryAfter }}s</strong>. Los usuarios del backoffice navegan de forma transparente con bypass.
                        @else
                            El portal está abierto para todos los usuarios y motores de búsqueda en todos los mercados.
                        @endif
                    </p>
                </div>
            </div>
        </div>

        <div class="shrink-0 flex flex-col sm:flex-row md:flex-col items-start md:items-end gap-2 text-[11px] text-[color:var(--admin-muted)] font-mono">
            <div>
                <span class="text-[color:var(--admin-muted)]">{{ __('admin.maintenance.bypass_staff') }}</span>
                <span class="text-[color:var(--admin-accent)] font-bold">{{ __('admin.maintenance.enabled') }}</span>
            </div>
            <div>
                <span class="text-[color:var(--admin-muted)]">{{ __('admin.maintenance.deadline') }}</span>
                <span class="text-[color:var(--admin-text)] font-bold">{{ $endDateTime ? $endDateTime->format('d/m/Y H:i') : __('admin.maintenance.not_scheduled') }}</span>
            </div>
        </div>
    </x-admin.form-card>

    <!-- Main Configuration Form -->
    <form action="{{ route('admin.maintenance.save') }}" method="POST" class="space-y-6">
        @csrf

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left 2 Cols: Main Settings -->
            <div class="lg:col-span-2 space-y-6">

                <!-- Section 1: Activation & Temporal Schedule -->
                <x-admin.form-card class="space-y-5">
                    <div class="border-b border-[color:var(--admin-border)] pb-3 flex items-center justify-between">
                        <h2 class="text-xs font-bold text-[color:var(--admin-text)] uppercase tracking-wider flex items-center gap-2">
                            <span class="text-[color:var(--admin-accent)]">&gt;</span> 1. Activación y Programación de Fecha
                        </h2>
                        <span class="text-[10px] text-[color:var(--admin-muted)] font-mono">{{ __('admin.maintenance.seo_title') }}</span>
                    </div>

                    <div class="space-y-4">
                        <div class="p-3 bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] rounded-lg flex items-center justify-between">
                            <div>
                                <label for="is_enabled" class="admin-field-label font-bold text-[color:var(--admin-text)] text-xs block cursor-pointer">{{ __('admin.maintenance.activate') }}</label>
                                <p class="text-[color:var(--admin-muted)] text-[11px] mt-0.5">{{ __('admin.maintenance.status_desc') }}</p>
                            </div>
                            <input type="checkbox" name="is_enabled" id="is_enabled" value="1" {{ $isActive ? 'checked' : '' }} class="w-5 h-5 rounded bg-[color:var(--admin-surface)] border-[color:var(--admin-border)] text-[color:var(--admin-accent)] focus:ring-0 cursor-pointer">
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="end_datetime" class="admin-field-label font-bold text-[color:var(--admin-text)] text-xs block mb-1">
                                    Fecha y Hora Estimada de Finalización (Reapertura)
                                </label>
                                <p class="text-[11px] text-[color:var(--admin-muted)] mb-2">{!! __('admin.maintenance.retry_desc') !!}</p>
                                <input type="datetime-local"
                                       name="end_datetime"
                                       id="end_datetime"
                                       value="{{ $settings['end_datetime'] ?? '' }}"
                                       class="admin-control w-full transition-colors">
                            </div>

                            <div>
                                <label for="retry_after_fallback" class="admin-field-label font-bold text-[color:var(--admin-text)] text-xs block mb-1">
                                    Retry-After por Defecto (Segundos)
                                </label>
                                <p class="text-[11px] text-[color:var(--admin-muted)] mb-2">{{ __('admin.maintenance.retry_hint') }}</p>
                                <input type="number"
                                       name="retry_after_fallback"
                                       id="retry_after_fallback"
                                       value="{{ $settings['retry_after_fallback'] ?? 3600 }}"
                                       min="30"
                                       class="admin-control w-full transition-colors">
                            </div>
                        </div>

                        <div class="pt-1">
                            <label class="inline-flex items-center gap-2.5 cursor-pointer">
                                <input type="checkbox" name="show_countdown" id="show_countdown" value="1" {{ !empty($settings['show_countdown']) ? 'checked' : '' }} class="w-4 h-4 rounded bg-[color:var(--admin-surface)] border-[color:var(--admin-border)] text-[color:var(--admin-accent)] focus:ring-0 cursor-pointer">
                                <span class="text-xs text-[color:var(--admin-text)] select-none">{{ __('admin.maintenance.show_countdown') }}</span>
                            </label>
                        </div>
                    </div>
                </x-admin.form-card>

                <!-- Section 2: Page Customization Mode -->
                <x-admin.form-card class="space-y-5">
                    <div class="border-b border-[color:var(--admin-border)] pb-3 flex items-center justify-between">
                        <h2 class="text-xs font-bold text-[color:var(--admin-text)] uppercase tracking-wider flex items-center gap-2">
                            <span class="text-[color:var(--admin-accent)]">&gt;</span> 2. Personalización Visual (Crear la página al gusto)
                        </h2>
                        <span class="text-[10px] text-[color:var(--admin-muted)] font-mono">{{ __('admin.maintenance.presentation') }}</span>
                    </div>

                    <!-- Mode Selector Radio -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <label @click="mode = 'default'" class="p-3.5 rounded-lg border cursor-pointer transition-all flex items-start gap-3" :class="mode === 'default' ? 'bg-[color:var(--admin-surface)] border-[color:var(--admin-accent)] shadow-sm' : 'bg-[color:var(--admin-surface)]/40 border-[color:var(--admin-border)] hover:border-[color:var(--admin-border)]'">
                            <input type="radio" name="mode" value="default" x-model="mode" class="mt-0.5 text-[color:var(--admin-accent)] focus:ring-0">
                            <div>
                                <span class="text-xs font-bold text-[color:var(--admin-text)] block">{{ __('admin.maintenance.template_cyber') }}</span>
                                <span class="text-[11px] text-[color:var(--admin-muted)] block mt-0.5">{{ __('admin.maintenance.template_desc') }}</span>
                            </div>
                        </label>

                        <label @click="mode = 'custom_html'" class="p-3.5 rounded-lg border cursor-pointer transition-all flex items-start gap-3" :class="mode === 'custom_html' ? 'bg-[color:var(--admin-surface)] border-[color:var(--admin-accent)] shadow-sm' : 'bg-[color:var(--admin-surface)]/40 border-[color:var(--admin-border)] hover:border-[color:var(--admin-border)]'">
                            <input type="radio" name="mode" value="custom_html" x-model="mode" class="mt-0.5 text-[color:var(--admin-accent)] focus:ring-0">
                            <div>
                                <span class="text-xs font-bold text-[color:var(--admin-text)] block">{{ __('admin.maintenance.template_custom') }}</span>
                                <span class="text-[11px] text-[color:var(--admin-muted)] block mt-0.5">{{ __('admin.maintenance.template_custom_desc') }}</span>
                            </div>
                        </label>
                    </div>

                    <!-- Default Template Options -->
                    <div x-show="mode === 'default'" class="space-y-4 pt-2">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="title" class="admin-field-label font-bold text-[color:var(--admin-text)] text-xs block mb-1">{{ __('admin.maintenance.tab_title') }}</label>
                                <input type="text" name="title" id="title" value="{{ $settings['title'] ?? 'David Bravo' }}" class="admin-control w-full">
                            </div>

                            <div>
                                <label for="logo_text" class="admin-field-label font-bold text-[color:var(--admin-text)] text-xs block mb-1">{{ __('admin.maintenance.logo_text') }}</label>
                                <input type="text" name="logo_text" id="logo_text" value="{{ $settings['logo_text'] ?? '>_db' }}" placeholder=">_db" class="admin-control w-full">
                            </div>

                            <div>
                                <label for="accent_color" class="admin-field-label font-bold text-[color:var(--admin-text)] text-xs block mb-1">{{ __('admin.maintenance.accent') }}</label>
                                <div class="flex items-center gap-3">
                                    <input type="color" name="accent_color" id="accent_color" value="{{ $settings['accent_color'] ?? '#00FF99' }}" oninput="document.getElementById('accent_val').textContent = this.value" class="w-10 h-10 rounded border border-[color:var(--admin-border)] bg-[color:var(--admin-surface)] cursor-pointer p-0.5">
                                    <span id="accent_val" class="font-mono text-xs text-[color:var(--admin-accent)]">{{ $settings['accent_color'] ?? '#00FF99' }}</span>
                                </div>
                            </div>

                            <div>
                                <label for="bg_color" class="admin-field-label font-bold text-[color:var(--admin-text)] text-xs block mb-1">{{ __('admin.maintenance.bg') }}</label>
                                <div class="flex items-center gap-3">
                                    <input type="color" name="bg_color" id="bg_color" value="{{ $settings['bg_color'] ?? '#1A1A1A' }}" oninput="document.getElementById('bg_val').textContent = this.value" class="w-10 h-10 rounded border border-[color:var(--admin-border)] bg-[color:var(--admin-surface)] cursor-pointer p-0.5">
                                    <span id="bg_val" class="font-mono text-xs text-[color:var(--admin-text)]">{{ $settings['bg_color'] ?? '#1A1A1A' }}</span>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label for="headline" class="admin-field-label font-bold text-[color:var(--admin-text)] text-xs block mb-1">{{ __('admin.maintenance.secondary') }}</label>
                            <input type="text" name="headline" id="headline" value="{{ $settings['headline'] ?? 'PLATAFORMA EN CONSTRUCCIÓN' }}" class="admin-control w-full">
                        </div>

                        <div>
                            <label for="message" class="admin-field-label font-bold text-[color:var(--admin-text)] text-xs block mb-1">{{ __('admin.maintenance.message') }}</label>
                            <textarea name="message" id="message" rows="3" class="admin-control w-full leading-relaxed">{{ $settings['message'] ?? '' }}</textarea>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                            <label class="inline-flex items-center gap-2.5 cursor-pointer">
                                <input type="checkbox" name="show_download_svg" id="show_download_svg" value="1" {{ !empty($settings['show_download_svg']) ? 'checked' : '' }} class="w-4 h-4 rounded bg-[color:var(--admin-surface)] border-[color:var(--admin-border)] text-[color:var(--admin-accent)] focus:ring-0 cursor-pointer">
                                <span class="text-xs text-[color:var(--admin-text)] select-none">{{ __('admin.maintenance.show_svg') }}</span>
                            </label>

                            <label class="inline-flex items-center gap-2.5 cursor-pointer">
                                <input type="checkbox" name="show_admin_link" id="show_admin_link" value="1" {{ !empty($settings['show_admin_link']) ? 'checked' : '' }} class="w-4 h-4 rounded bg-[color:var(--admin-surface)] border-[color:var(--admin-border)] text-[color:var(--admin-accent)] focus:ring-0 cursor-pointer">
                                <span class="text-xs text-[color:var(--admin-text)] select-none">{{ __('admin.maintenance.show_admin_link') }}</span>
                            </label>
                        </div>
                    </div>

                    <!-- Custom HTML Mode -->
                    <div x-show="mode === 'custom_html'" class="space-y-2 pt-2" style="display: none;">
                        <label for="custom_html" class="admin-field-label font-bold text-[color:var(--admin-text)] text-xs block">
                            Código HTML / Blade Personalizado al gusto:
                        </label>
                        <p class="text-[color:var(--admin-muted)] text-[11px]">{{ __('admin.maintenance.html_hint') }}</p>
                        <textarea name="custom_html" id="custom_html" rows="12" placeholder="<!DOCTYPE html><html><head>...</head><body>...</body></html>" class="admin-control w-full transition-colors focus:outline-none placeholder-[color:var(--admin-muted)] leading-relaxed">{{ $settings['custom_html'] ?? '' }}</textarea>
                    </div>
                </x-admin.form-card>

                <!-- Action Submit Bar -->
                <div class="flex items-center justify-between pt-2">
                    <x-admin.button :href="route('admin.settings.index')" variant="ghost">&larr; Volver a ajustes</x-admin.button>

                    <x-admin.button variant="primary" type="submit" class="text-xs py-2.5 px-6 shadow-sm">
                        <i class="fa-solid fa-floppy-disk mr-1.5"></i>
                        Guardar Configuración
                    </x-admin.button>
                </div>
            </div>

            <!-- Right 1 Col: Live Preview Iframe & Information -->
            <div class="space-y-6">
                <!-- Preview Shell -->
                <x-admin.form-card class="space-y-3">
                    <div class="flex items-center justify-between border-b border-[color:var(--admin-border)] pb-2">
                        <h3 class="text-xs font-bold text-[color:var(--admin-text)] flex items-center gap-2">
                            <i class="fa-solid fa-eye text-[color:var(--admin-accent)]"></i>
                            <span>{{ __('admin.maintenance.preview_live') }}</span>
                        </h3>
                        <div class="flex items-center gap-1 bg-[color:var(--admin-surface)] p-0.5 rounded border border-[color:var(--admin-border)]">
                            <x-admin.button variant="ghost" size="sm" alpine-active="previewDevice === 'desktop'" type="button" @click="previewDevice = 'desktop'" title="{{ __('admin.maintenance.desktop') }}">
                                <i class="fa-solid fa-desktop"></i>
                            </x-admin.button>
                            <x-admin.button variant="ghost" size="sm" alpine-active="previewDevice === 'mobile'" type="button" @click="previewDevice = 'mobile'" title="{{ __('admin.maintenance.mobile') }}">
                                <i class="fa-solid fa-mobile-screen"></i>
                            </x-admin.button>
                        </div>
                    </div>

                    <div class="relative bg-[color:var(--admin-surface)] rounded-lg overflow-hidden border border-[color:var(--admin-border)] flex justify-center items-center" style="height: 480px;">
                        <iframe src="{{ route('admin.maintenance.preview') }}"
                                class="w-full h-full border-0 transition-all duration-300"
                                :style="previewDevice === 'mobile' ? 'max-width: 320px; height: 100%; border: 1px solid #333; border-radius: 8px;' : 'width: 100%; height: 100%;'"
                                title="{{ __('admin.maintenance.preview_title') }}"></iframe>
                    </div>

                    <div class="pt-1 text-center">
                        <x-admin.button :href="route('admin.maintenance.preview')" target="_blank" rel="noopener" variant="ghost" size="sm">
                            <span>{{ __('admin.maintenance.fullscreen') }}</span>
                            <i class="fa-solid fa-arrow-up-right-from-square text-[9px]"></i>
                        </x-admin.button>
                    </div>
                </x-admin.form-card>

                <!-- Security & Governance Architecture Details -->
                <div class="p-4 rounded-xl bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] text-[color:var(--admin-muted)] text-xs space-y-2">
                    <div class="font-bold text-[color:var(--admin-text)] flex items-center gap-1.5 text-xs">
                        <i class="fa-solid fa-shield-halved text-[color:var(--admin-accent)]"></i>
                        <span>{{ __('admin.maintenance.bypass_title') }}</span>
                    </div>
                    <ul class="space-y-1.5 text-[11px] text-[color:var(--admin-muted)] list-disc list-inside">
                        <li>{!! __('admin.maintenance.bypass_routes_line') !!}</li>
                        <li>{{ __('admin.maintenance.bypass_users') }}</li>
                        <li>{!! __('admin.maintenance.bypass_health') !!}</li>
                        <li>{!! __('admin.maintenance.bypass_response') !!}</li>
                    </ul>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
