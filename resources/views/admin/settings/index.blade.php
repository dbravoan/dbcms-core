@extends('admin.layout')

@section('title', __('admin.page_titles.settings'))

@section('content')
<div class="admin-page space-y-6 max-w-5xl mx-auto pb-16 font-mono text-xs" x-data="siteSettings()">
    <!-- Breadcrumb Header -->
    <div class="flex items-center gap-2 text-[color:var(--admin-muted)] text-xs">
        <a href="{{ route('admin.dashboard') }}" class="hover:text-[color:var(--admin-text)] transition-colors">{{ __('admin.settings.panel') }}</a>
        <span>/</span>
        <span class="text-[color:var(--admin-muted)]">{{ __('admin.settings.system') }}</span>
        <span>/</span>
        <span class="text-[color:var(--admin-accent)] font-bold">{{ __('admin.nav.items.site_settings') }}</span>
    </div>

    <x-admin.form-header :title="__('admin.settings.title')" :description="__('admin.settings.subtitle')" :back-route="route('admin.dashboard')" back-label="Volver al panel" />

    <!-- Navigation Tabs -->
    <div class="flex items-center gap-2 border-b border-[color:var(--admin-border)] pb-2 overflow-x-auto">
        <button type="button" @click="activeTab = 'general'"
                :class="activeTab === 'general' ? 'bg-[color:var(--admin-accent)]/10 text-[color:var(--admin-accent)] border-[color:var(--admin-accent)]/30' : 'bg-[color:var(--admin-surface)] text-[color:var(--admin-muted)] border-[color:var(--admin-border)] hover:text-[color:var(--admin-text)]'"
                class="px-3.5 py-2 rounded-lg border text-xs font-bold transition-all flex items-center gap-2 shrink-0">
            <i class="fa-solid fa-globe"></i>
            <span>{{ __('admin.settings.tab_identity') }}</span>
        </button>

        <button type="button" @click="activeTab = 'legal'"
                :class="activeTab === 'legal' ? 'bg-[color:var(--admin-accent)]/10 text-[color:var(--admin-accent)] border-[color:var(--admin-accent)]/30' : 'bg-[color:var(--admin-surface)] text-[color:var(--admin-muted)] border-[color:var(--admin-border)] hover:text-[color:var(--admin-text)]'"
                class="px-3.5 py-2 rounded-lg border text-xs font-bold transition-all flex items-center gap-2 shrink-0">
            <i class="fa-solid fa-scale-balanced"></i>
            <span>{{ __('admin.settings.tab_fiscal') }}</span>
        </button>

        <button type="button" @click="activeTab = 'contact'"
                :class="activeTab === 'contact' ? 'bg-[color:var(--admin-accent)]/10 text-[color:var(--admin-accent)] border-[color:var(--admin-accent)]/30' : 'bg-[color:var(--admin-surface)] text-[color:var(--admin-muted)] border-[color:var(--admin-border)] hover:text-[color:var(--admin-text)]'"
                class="px-3.5 py-2 rounded-lg border text-xs font-bold transition-all flex items-center gap-2 shrink-0">
            <i class="fa-solid fa-headset"></i>
            <span>{{ __('admin.settings.tab_contact') }}</span>
        </button>

        <button type="button" @click="activeTab = 'gdpr'"
                :class="activeTab === 'gdpr' ? 'bg-[color:var(--admin-accent)]/10 text-[color:var(--admin-accent)] border-[color:var(--admin-accent)]/30' : 'bg-[color:var(--admin-surface)] text-[color:var(--admin-muted)] border-[color:var(--admin-border)] hover:text-[color:var(--admin-text)]'"
                class="px-3.5 py-2 rounded-lg border text-xs font-bold transition-all flex items-center gap-2 shrink-0">
            <i class="fa-solid fa-shield-halved"></i>
            <span>{{ __('admin.settings.tab_gdpr') }}</span>
        </button>

        <button type="button" @click="activeTab = 'social'"
                :class="activeTab === 'social' ? 'bg-[color:var(--admin-accent)]/10 text-[color:var(--admin-accent)] border-[color:var(--admin-accent)]/30' : 'bg-[color:var(--admin-surface)] text-[color:var(--admin-muted)] border-[color:var(--admin-border)] hover:text-[color:var(--admin-text)]'"
                class="px-3.5 py-2 rounded-lg border text-xs font-bold transition-all flex items-center gap-2 shrink-0">
            <i class="fa-solid fa-share-nodes"></i>
            <span>{{ __('admin.settings.tab_social') }}</span>
        </button>

        <button type="button" @click="activeTab = 'seo'"
                :class="activeTab === 'seo' ? 'bg-[color:var(--admin-accent)]/10 text-[color:var(--admin-accent)] border-[color:var(--admin-accent)]/30' : 'bg-[color:var(--admin-surface)] text-[color:var(--admin-muted)] border-[color:var(--admin-border)] hover:text-[color:var(--admin-text)]'"
                class="px-3.5 py-2 rounded-lg border text-xs font-bold transition-all flex items-center gap-2 shrink-0">
            <i class="fa-solid fa-location-dot"></i>
            <span>{{ __('admin.settings.tab_seo') }}</span>
        </button>

        <button type="button" @click="activeTab = 'nav'"
                :class="activeTab === 'nav' ? 'bg-[color:var(--admin-accent)]/10 text-[color:var(--admin-accent)] border-[color:var(--admin-accent)]/30' : 'bg-[color:var(--admin-surface)] text-[color:var(--admin-muted)] border-[color:var(--admin-border)] hover:text-[color:var(--admin-text)]'"
                class="px-3.5 py-2 rounded-lg border text-xs font-bold transition-all flex items-center gap-2 shrink-0">
            <i class="fa-solid fa-bars"></i>
            <span>Navegación</span>
        </button>
    </div>

    <!-- Main Settings Form -->
    <form action="{{ route('admin.settings.update') }}" method="POST" class="space-y-6">
        @csrf
        @method('PUT')

        <!-- TAB 1: General & Marca -->
        <x-admin.form-card x-show="activeTab === 'general'" class="sm:p-7 space-y-6">
            <div>
                <h2 class="text-sm font-bold text-[color:var(--admin-text)] flex items-center gap-2">
                    <span class="text-[color:var(--admin-accent)]">&gt;</span> Identidad de Marca y Parámetros Globales
                </h2>
                <p class="text-[color:var(--admin-muted)] text-xs mt-1">{{ __('admin.settings.identity_desc') }}</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-4 border-t border-[color:var(--admin-border)]/80">
                <div class="space-y-1.5 md:col-span-2">
                    <label class="admin-field-label font-bold text-[color:var(--admin-text)] text-xs">{{ __('admin.settings.site_name') }}</label>
                    <input type="text" name="general_site_name" value="{{ old('general_site_name', $settings['general.site_name'] ?? config('cms.name', 'CMS')) }}"
                           class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] focus:border-[color:var(--admin-accent)] rounded-lg px-3 py-2 text-[color:var(--admin-text)] text-xs outline-none transition-colors"
                           placeholder="{{ __('admin.settings.ph_site_name') }}">
                    <p class="text-[11px] text-[color:var(--admin-muted)]">{{ __('admin.settings.site_name_hint') }}</p>
                </div>

                <div class="space-y-1.5 md:col-span-2">
                    <label class="admin-field-label font-bold text-[color:var(--admin-text)] text-xs">{{ __('admin.settings.tagline') }}</label>
                    <input type="text" name="general_site_tagline" value="{{ old('general_site_tagline', $settings['general.site_tagline'] ?? '') }}"
                           class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] focus:border-[color:var(--admin-accent)] rounded-lg px-3 py-2 text-[color:var(--admin-text)] text-xs outline-none transition-colors"
                           placeholder="{{ __('admin.settings.ph_tagline') }}">
                </div>

                <div class="space-y-1.5 md:col-span-2">
                    <label class="admin-field-label font-bold text-[color:var(--admin-text)] text-xs">{{ __('admin.settings.meta_description') }}</label>
                    <textarea name="general_site_description" rows="3"
                              class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] focus:border-[color:var(--admin-accent)] rounded-lg px-3 py-2 text-[color:var(--admin-text)] text-xs outline-none transition-colors"
                              placeholder="{{ __('admin.settings.ph_meta_desc') }}">{{ old('general_site_description', $settings['general.site_description'] ?? '') }}</textarea>
                </div>

                <x-admin.media-field name="general_site_logo" field="site_logo" label="Logotipo Principal (Fondo Claro)" hint="Se usa en el header público y en el grafo Schema.org Organization." />
                <x-admin.media-field name="general_site_logo_dark" field="site_logo_dark" label="Logotipo Modo Oscuro (Opcional)" />
                <x-admin.media-field name="general_site_favicon" field="site_favicon" label="Favicon" hint="PNG/SVG/ICO cuadrado (recomendado 512×512)." />
                <x-admin.media-field name="general_og_image" field="og_image" label="Imagen OpenGraph (OG Image) / Social" hint="Recomendado 1200×630. Se usa al compartir en redes y motores con IA." />

                <div class="space-y-1.5">
                    <label class="admin-field-label font-bold text-[color:var(--admin-text)] text-xs">{{ __('admin.settings.copyright') }}</label>
                    <input type="text" name="general_footer_text" value="{{ old('general_footer_text', $settings['general.footer_text'] ?? '') }}"
                           class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] focus:border-[color:var(--admin-accent)] rounded-lg px-3 py-2 text-[color:var(--admin-text)] text-xs outline-none transition-colors"
                           placeholder="{{ __('admin.settings.ph_copyright') }}">
                </div>
            </div>
        </x-admin.form-card>

        <!-- TAB 2: Datos Fiscales & LSSI -->
        <x-admin.form-card x-show="activeTab === 'legal'" class="sm:p-7 space-y-6">
            <div>
                <h2 class="text-sm font-bold text-[color:var(--admin-text)] flex items-center gap-2">
                    <span class="text-[color:var(--admin-accent)]">&gt;</span> Datos Fiscales y Registro Mercantil (Avisos Legales LSSI)
                </h2>
                <p class="text-[color:var(--admin-muted)] text-xs mt-1">{{ __('admin.settings.fiscal_desc') }}</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-4 border-t border-[color:var(--admin-border)]/80">
                <div class="space-y-1.5">
                    <label class="admin-field-label font-bold text-[color:var(--admin-text)] text-xs">{{ __('admin.settings.legal_name') }}</label>
                    <input type="text" name="legal_company_name" value="{{ old('legal_company_name', $settings['legal.company_name'] ?? '') }}"
                           class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] focus:border-[color:var(--admin-accent)] rounded-lg px-3 py-2 text-[color:var(--admin-text)] text-xs outline-none transition-colors"
                           placeholder="{{ __('admin.settings.ph_legal_name') }}">
                </div>

                <div class="space-y-1.5">
                    <label class="admin-field-label font-bold text-[color:var(--admin-text)] text-xs">{{ __('admin.settings.tax_id') }}</label>
                    <input type="text" name="legal_tax_id" value="{{ old('legal_tax_id', $settings['legal.tax_id'] ?? '') }}"
                           class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] focus:border-[color:var(--admin-accent)] rounded-lg px-3 py-2 text-[color:var(--admin-text)] text-xs outline-none transition-colors"
                           placeholder="{{ __('admin.settings.ph_tax_id') }}">
                </div>

                <div class="space-y-1.5 md:col-span-2">
                    <label class="admin-field-label font-bold text-[color:var(--admin-text)] text-xs">{{ __('admin.settings.registry') }}</label>
                    <input type="text" name="legal_commercial_registry" value="{{ old('legal_commercial_registry', $settings['legal.commercial_registry'] ?? '') }}"
                           class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] focus:border-[color:var(--admin-accent)] rounded-lg px-3 py-2 text-[color:var(--admin-text)] text-xs outline-none transition-colors"
                           placeholder="{{ __('admin.settings.ph_registry') }}">
                </div>

                <div class="space-y-1.5 md:col-span-2">
                    <label class="admin-field-label font-bold text-[color:var(--admin-text)] text-xs">{{ __('admin.settings.address') }}</label>
                    <input type="text" name="legal_address" value="{{ old('legal_address', $settings['legal.address'] ?? '') }}"
                           class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] focus:border-[color:var(--admin-accent)] rounded-lg px-3 py-2 text-[color:var(--admin-text)] text-xs outline-none transition-colors"
                           placeholder="{{ __('admin.settings.ph_address') }}">
                </div>

                <div class="space-y-1.5">
                    <label class="admin-field-label font-bold text-[color:var(--admin-text)] text-xs">{{ __('admin.settings.postal_code') }}</label>
                    <input type="text" name="legal_postal_code" value="{{ old('legal_postal_code', $settings['legal.postal_code'] ?? '') }}"
                           class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] focus:border-[color:var(--admin-accent)] rounded-lg px-3 py-2 text-[color:var(--admin-text)] text-xs outline-none transition-colors"
                           placeholder="{{ __('admin.settings.ph_postal') }}">
                </div>

                <div class="space-y-1.5">
                    <label class="admin-field-label font-bold text-[color:var(--admin-text)] text-xs">{{ __('admin.settings.city') }}</label>
                    <input type="text" name="legal_city" value="{{ old('legal_city', $settings['legal.city'] ?? '') }}"
                           class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] focus:border-[color:var(--admin-accent)] rounded-lg px-3 py-2 text-[color:var(--admin-text)] text-xs outline-none transition-colors"
                           placeholder="{{ __('admin.settings.ph_city') }}">
                </div>

                <div class="space-y-1.5">
                    <label class="admin-field-label font-bold text-[color:var(--admin-text)] text-xs">{{ __('admin.settings.province') }}</label>
                    <input type="text" name="legal_province" value="{{ old('legal_province', $settings['legal.province'] ?? '') }}"
                           class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] focus:border-[color:var(--admin-accent)] rounded-lg px-3 py-2 text-[color:var(--admin-text)] text-xs outline-none transition-colors"
                           placeholder="{{ __('admin.settings.ph_city') }}">
                </div>

                <div class="space-y-1.5">
                    <label class="admin-field-label font-bold text-[color:var(--admin-text)] text-xs">{{ __('admin.settings.country') }}</label>
                    <input type="text" name="legal_country" value="{{ old('legal_country', $settings['legal.country'] ?? 'España') }}"
                           class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] focus:border-[color:var(--admin-accent)] rounded-lg px-3 py-2 text-[color:var(--admin-text)] text-xs outline-none transition-colors"
                           placeholder="{{ __('admin.settings.ph_country') }}">
                </div>

                <div class="space-y-1.5 md:col-span-2">
                    <label class="admin-field-label font-bold text-[color:var(--admin-text)] text-xs">{{ __('admin.settings.activity') }}</label>
                    <input type="text" name="legal_activity" value="{{ old('legal_activity', $settings['legal.activity'] ?? '') }}"
                           class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] focus:border-[color:var(--admin-accent)] rounded-lg px-3 py-2 text-[color:var(--admin-text)] text-xs outline-none transition-colors"
                           placeholder="{{ __('admin.settings.ph_activity') }}">
                </div>
            </div>
        </x-admin.form-card>

        <!-- TAB 3: Contacto & Soporte -->
        <x-admin.form-card x-show="activeTab === 'contact'" class="sm:p-7 space-y-6">
            <div>
                <h2 class="text-sm font-bold text-[color:var(--admin-text)] flex items-center gap-2">
                    <span class="text-[color:var(--admin-accent)]">&gt;</span> Canales de Contacto y Atención al Cliente
                </h2>
                <p class="text-[color:var(--admin-muted)] text-xs mt-1">{{ __('admin.settings.contact_desc') }}</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-4 border-t border-[color:var(--admin-border)]/80">
                <div class="space-y-1.5">
                    <label class="admin-field-label font-bold text-[color:var(--admin-text)] text-xs">{{ __('admin.settings.email_main') }}</label>
                    <input type="email" name="contact_email" value="{{ old('contact_email', $settings['contact.email'] ?? '') }}"
                           class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] focus:border-[color:var(--admin-accent)] rounded-lg px-3 py-2 text-[color:var(--admin-text)] text-xs outline-none transition-colors"
                           placeholder="{{ __('admin.settings.ph_email_main') }}">
                </div>

                <div class="space-y-1.5">
                    <label class="admin-field-label font-bold text-[color:var(--admin-text)] text-xs">{{ __('admin.settings.email_support') }}</label>
                    <input type="email" name="contact_support_email" value="{{ old('contact_support_email', $settings['contact.support_email'] ?? '') }}"
                           class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] focus:border-[color:var(--admin-accent)] rounded-lg px-3 py-2 text-[color:var(--admin-text)] text-xs outline-none transition-colors"
                           placeholder="{{ __('admin.settings.ph_email_support') }}">
                </div>

                <div class="space-y-1.5">
                    <label class="admin-field-label font-bold text-[color:var(--admin-text)] text-xs">{{ __('admin.settings.phone_main') }}</label>
                    <input type="text" name="contact_phone" value="{{ old('contact_phone', $settings['contact.phone'] ?? '') }}"
                           class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] focus:border-[color:var(--admin-accent)] rounded-lg px-3 py-2 text-[color:var(--admin-text)] text-xs outline-none transition-colors"
                           placeholder="+34 912 345 678">
                </div>

                <div class="space-y-1.5">
                    <label class="admin-field-label font-bold text-[color:var(--admin-text)] text-xs">{{ __('admin.settings.phone_whatsapp') }}</label>
                    <input type="text" name="contact_whatsapp" value="{{ old('contact_whatsapp', $settings['contact.whatsapp'] ?? '') }}"
                           class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] focus:border-[color:var(--admin-accent)] rounded-lg px-3 py-2 text-[color:var(--admin-text)] text-xs outline-none transition-colors"
                           placeholder="+34 600 000 000">
                </div>

                <div class="space-y-1.5 md:col-span-2">
                    <label class="admin-field-label font-bold text-[color:var(--admin-text)] text-xs">{{ __('admin.settings.hours') }}</label>
                    <input type="text" name="contact_schedule" value="{{ old('contact_schedule', $settings['contact.schedule'] ?? '') }}"
                           class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] focus:border-[color:var(--admin-accent)] rounded-lg px-3 py-2 text-[color:var(--admin-text)] text-xs outline-none transition-colors"
                           placeholder="{{ __('admin.settings.ph_hours') }}">
                </div>
            </div>
        </x-admin.form-card>

        <!-- TAB 4: RGPD & DPO -->
        <x-admin.form-card x-show="activeTab === 'gdpr'" class="sm:p-7 space-y-6">
            <div>
                <h2 class="text-sm font-bold text-[color:var(--admin-text)] flex items-center gap-2">
                    <span class="text-[color:var(--admin-accent)]">&gt;</span> Cumplimiento RGPD y Delegado de Protección de Datos
                </h2>
                <p class="text-[color:var(--admin-muted)] text-xs mt-1">{{ __('admin.settings.gdpr_desc') }}</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-4 border-t border-[color:var(--admin-border)]/80">
                <div class="space-y-1.5 md:col-span-2">
                    <label for="legal_dpo_contact" class="admin-field-label font-bold text-[color:var(--admin-text)] text-xs">{{ __('legal.dpo') }}</label>
                    <input id="legal_dpo_contact" type="text" maxlength="500" name="legal_dpo_contact" value="{{ old('legal_dpo_contact', $settings['legal.dpo_contact'] ?? '') }}"
                           class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] focus:border-[color:var(--admin-accent)] rounded-lg px-3 py-2 text-[color:var(--admin-text)] text-xs outline-none transition-colors"
                           placeholder="{{ __('admin.settings.ph_dpo') }}">
                </div>

                <div class="space-y-1.5 md:col-span-2">
                    <label for="legal_privacy_email" class="admin-field-label font-bold text-[color:var(--admin-text)] text-xs">{{ __('legal.privacy_contact') }}</label>
                    <input id="legal_privacy_email" type="email" maxlength="255" name="legal_privacy_email" value="{{ old('legal_privacy_email', $settings['legal.privacy_email'] ?? '') }}"
                           class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] focus:border-[color:var(--admin-accent)] rounded-lg px-3 py-2 text-[color:var(--admin-text)] text-xs outline-none transition-colors">
                </div>

                <div class="space-y-1.5">
                    <label class="admin-field-label font-bold text-[color:var(--admin-text)] text-xs">{{ __('admin.settings.legal_url') }}</label>
                    <input type="text" readonly name="gdpr_page_notice" value="{{ old('gdpr_page_notice', $settings['gdpr.page_notice'] ?? 'aviso-legal') }}"
                           class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] focus:border-[color:var(--admin-accent)] rounded-lg px-3 py-2 text-[color:var(--admin-text)] text-xs outline-none transition-colors"
                           placeholder="{{ __('admin.settings.ph_legal_url') }}">
                </div>

                <div class="space-y-1.5">
                    <label class="admin-field-label font-bold text-[color:var(--admin-text)] text-xs">{{ __('admin.settings.privacy_url') }}</label>
                    <input type="text" readonly name="gdpr_page_privacy" value="{{ old('gdpr_page_privacy', $settings['gdpr.page_privacy'] ?? 'privacidad') }}"
                           class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] focus:border-[color:var(--admin-accent)] rounded-lg px-3 py-2 text-[color:var(--admin-text)] text-xs outline-none transition-colors"
                           placeholder="{{ __('admin.settings.ph_privacy_url') }}">
                </div>

                <div class="space-y-1.5">
                    <label class="admin-field-label font-bold text-[color:var(--admin-text)] text-xs">{{ __('admin.settings.terms_url') }}</label>
                    <input type="text" name="gdpr_page_terms" value="{{ old('gdpr_page_terms', $settings['gdpr.page_terms'] ?? 'terminos') }}"
                           class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] focus:border-[color:var(--admin-accent)] rounded-lg px-3 py-2 text-[color:var(--admin-text)] text-xs outline-none transition-colors"
                           placeholder="{{ __('admin.settings.ph_terms_url') }}">
                </div>

                <div class="space-y-1.5">
                    <label class="admin-field-label font-bold text-[color:var(--admin-text)] text-xs">{{ __('admin.settings.cookies_url') }}</label>
                    <input type="text" readonly name="gdpr_page_cookies" value="{{ old('gdpr_page_cookies', $settings['gdpr.page_cookies'] ?? 'cookies') }}"
                           class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] focus:border-[color:var(--admin-accent)] rounded-lg px-3 py-2 text-[color:var(--admin-text)] text-xs outline-none transition-colors"
                           placeholder="{{ __('admin.settings.ph_cookies_url') }}">
                </div>
            </div>
        </x-admin.form-card>

        <!-- TAB 5: Redes Sociales -->
        <x-admin.form-card x-show="activeTab === 'social'" class="sm:p-7 space-y-6">
            <div>
                <h2 class="text-sm font-bold text-[color:var(--admin-text)] flex items-center gap-2">
                    <span class="text-[color:var(--admin-accent)]">&gt;</span> Perfiles en Redes Sociales Oficiales
                </h2>
                <p class="text-[color:var(--admin-muted)] text-xs mt-1">{!! __('admin.settings.social_desc') !!}</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-4 border-t border-[color:var(--admin-border)]/80">
                <div class="space-y-1.5">
                    <label class="admin-field-label font-bold text-[color:var(--admin-text)] text-xs flex items-center gap-2">
                        <i class="fa-brands fa-x-twitter text-sm"></i> <span>X (Twitter)</span>
                    </label>
                    <input type="url" name="social_twitter" value="{{ old('social_twitter', $settings['social.twitter'] ?? '') }}"
                           class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] focus:border-[color:var(--admin-accent)] rounded-lg px-3 py-2 text-[color:var(--admin-text)] text-xs outline-none transition-colors"
                           placeholder="{{ __('admin.settings.ph_social_twitter') }}">
                </div>

                <div class="space-y-1.5">
                    <label class="admin-field-label font-bold text-[color:var(--admin-text)] text-xs flex items-center gap-2">
                        <i class="fa-brands fa-linkedin text-sm text-[#0A66C2]"></i> <span>LinkedIn</span>
                    </label>
                    <input type="url" name="social_linkedin" value="{{ old('social_linkedin', $settings['social.linkedin'] ?? '') }}"
                           class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] focus:border-[color:var(--admin-accent)] rounded-lg px-3 py-2 text-[color:var(--admin-text)] text-xs outline-none transition-colors"
                           placeholder="{{ __('admin.settings.ph_social_linkedin') }}">
                </div>

                <div class="space-y-1.5">
                    <label class="admin-field-label font-bold text-[color:var(--admin-text)] text-xs flex items-center gap-2">
                        <i class="fa-brands fa-instagram text-sm text-[#E4405F]"></i> <span>Instagram</span>
                    </label>
                    <input type="url" name="social_instagram" value="{{ old('social_instagram', $settings['social.instagram'] ?? '') }}"
                           class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] focus:border-[color:var(--admin-accent)] rounded-lg px-3 py-2 text-[color:var(--admin-text)] text-xs outline-none transition-colors"
                           placeholder="{{ __('admin.settings.ph_social_instagram') }}">
                </div>

                <div class="space-y-1.5">
                    <label class="admin-field-label font-bold text-[color:var(--admin-text)] text-xs flex items-center gap-2">
                        <i class="fa-brands fa-facebook text-sm text-[#1877F2]"></i> <span>Facebook</span>
                    </label>
                    <input type="url" name="social_facebook" value="{{ old('social_facebook', $settings['social.facebook'] ?? '') }}"
                           class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] focus:border-[color:var(--admin-accent)] rounded-lg px-3 py-2 text-[color:var(--admin-text)] text-xs outline-none transition-colors"
                           placeholder="{{ __('admin.settings.ph_social_facebook') }}">
                </div>

                <div class="space-y-1.5">
                    <label class="admin-field-label font-bold text-[color:var(--admin-text)] text-xs flex items-center gap-2">
                        <i class="fa-brands fa-youtube text-sm text-[#FF0000]"></i> <span>YouTube</span>
                    </label>
                    <input type="url" name="social_youtube" value="{{ old('social_youtube', $settings['social.youtube'] ?? '') }}"
                           class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] focus:border-[color:var(--admin-accent)] rounded-lg px-3 py-2 text-[color:var(--admin-text)] text-xs outline-none transition-colors"
                           placeholder="{{ __('admin.settings.ph_social_youtube') }}">
                </div>

                <div class="space-y-1.5">
                    <label class="admin-field-label font-bold text-[color:var(--admin-text)] text-xs flex items-center gap-2">
                        <i class="fa-brands fa-tiktok text-sm"></i> <span>TikTok</span>
                    </label>
                    <input type="url" name="social_tiktok" value="{{ old('social_tiktok', $settings['social.tiktok'] ?? '') }}"
                           class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] focus:border-[color:var(--admin-accent)] rounded-lg px-3 py-2 text-[color:var(--admin-text)] text-xs outline-none transition-colors"
                           placeholder="{{ __('admin.settings.ph_social_tiktok') }}">
                </div>

                <div class="space-y-1.5 md:col-span-2">
                    <label class="admin-field-label font-bold text-[color:var(--admin-text)] text-xs flex items-center gap-2">
                        <i class="fa-brands fa-github text-sm"></i> <span>GitHub</span>
                    </label>
                    <input type="url" name="social_github" value="{{ old('social_github', $settings['social.github'] ?? '') }}"
                           class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] focus:border-[color:var(--admin-accent)] rounded-lg px-3 py-2 text-[color:var(--admin-text)] text-xs outline-none transition-colors"
                           placeholder="{{ __('admin.settings.ph_social_github') }}">
                </div>
            </div>
        </x-admin.form-card>

        <!-- TAB 6: SEO Local & Schema.org -->
        <x-admin.form-card x-show="activeTab === 'seo'" class="sm:p-7 space-y-6">
            <div>
                <h2 class="text-sm font-bold text-[color:var(--admin-text)] flex items-center gap-2">
                    <span class="text-[color:var(--admin-accent)]">&gt;</span> SEO Local y Datos Estructurados Schema.org
                </h2>
                <p class="text-[color:var(--admin-muted)] text-xs mt-1">{!! __('admin.settings.seo_desc') !!}</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-4 border-t border-[color:var(--admin-border)]/80">
                <div class="space-y-1.5">
                    <label class="admin-field-label font-bold text-[color:var(--admin-text)] text-xs">{{ __('admin.settings.schema_type') }}</label>
                    <select name="seo_organization_type" class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] focus:border-[color:var(--admin-accent)] rounded-lg px-3 py-2 text-[color:var(--admin-text)] text-xs outline-none transition-colors">
                        @php $currentType = $settings['seo.organization_type'] ?? 'Organization'; @endphp
                        @foreach(config('cms.options.organization_schema_types') as $schemaValue => $schemaLabel)
                            <option value="{{ $schemaValue }}" {{ $currentType === $schemaValue ? 'selected' : '' }}>{{ __($schemaLabel) }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="space-y-1.5">
                    <label class="admin-field-label font-bold text-[color:var(--admin-text)] text-xs">{{ __('admin.settings.price_range') }}</label>
                    <input type="text" name="seo_price_range" value="{{ old('seo_price_range', $settings['seo.price_range'] ?? '€€') }}"
                           class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] focus:border-[color:var(--admin-accent)] rounded-lg px-3 py-2 text-[color:var(--admin-text)] text-xs outline-none transition-colors"
                           placeholder="{{ __('admin.settings.ph_price_range') }}">
                </div>

                <div class="space-y-1.5">
                    <label class="admin-field-label font-bold text-[color:var(--admin-text)] text-xs">{{ __('admin.settings.latitude') }}</label>
                    <input type="text" name="seo_latitude" value="{{ old('seo_latitude', $settings['seo.latitude'] ?? '') }}"
                           class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] focus:border-[color:var(--admin-accent)] rounded-lg px-3 py-2 text-[color:var(--admin-text)] text-xs outline-none transition-colors"
                           placeholder="{{ __('admin.settings.ph_latitude') }}">
                </div>

                <div class="space-y-1.5">
                    <label class="admin-field-label font-bold text-[color:var(--admin-text)] text-xs">{{ __('admin.settings.longitude') }}</label>
                    <input type="text" name="seo_longitude" value="{{ old('seo_longitude', $settings['seo.longitude'] ?? '') }}"
                           class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] focus:border-[color:var(--admin-accent)] rounded-lg px-3 py-2 text-[color:var(--admin-text)] text-xs outline-none transition-colors"
                           placeholder="{{ __('admin.settings.ph_longitude') }}">
                </div>
            </div>
        </x-admin.form-card>

        <!-- TAB: Navegación (front office) -->
        <x-admin.form-card x-show="activeTab === 'nav'" x-cloak class="sm:p-7 space-y-6">
            @php
                $navAdminButton = old('front_nav_admin_button', $settings['front.nav_admin_button'] ?? false);
                $navLayout = old('front_nav_layout', $settings['front.nav_layout'] ?? 'inline');
                $navToggleEnabled = old('front_nav_toggle_enabled', $settings['front.nav_toggle_enabled'] ?? true);
                $navToggleIcon = old('front_nav_toggle_icon', $settings['front.nav_toggle_icon'] ?? 'bars');
                $navToggleDesktop = old('front_nav_toggle_desktop', $settings['front.nav_toggle_desktop'] ?? false);
            @endphp

            <div>
                <h2 class="text-sm font-bold text-[color:var(--admin-text)] flex items-center gap-2">
                    <span class="text-[color:var(--admin-accent)]">&gt;</span> Navegación del Frontoffice
                </h2>
                <p class="text-[color:var(--admin-muted)] text-xs mt-1">Controla el menú de los temas públicos: acceso al panel, disposición de los enlaces y el icono para plegar el menú.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-4 border-t border-[color:var(--admin-border)]/80">
                <label class="flex items-start gap-3 md:col-span-2 p-3 rounded-lg bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] cursor-pointer">
                    <input type="checkbox" name="front_nav_admin_button" value="1"
                           class="mt-0.5 rounded bg-[color:var(--admin-surface)] border-[color:var(--admin-border)] text-[color:var(--admin-accent)] focus:ring-[color:var(--admin-accent)]"
                           {{ $navAdminButton ? 'checked' : '' }}>
                    <span>
                        <span class="block font-bold text-[color:var(--admin-text)] text-xs">Mostrar botón de acceso al backoffice</span>
                        <span class="block text-[11px] text-[color:var(--admin-muted)]">Desactivado por defecto: el enlace a /admin se oculta en el navbar público.</span>
                    </span>
                </label>

                <div class="space-y-1.5">
                    <label class="admin-field-label font-bold text-[color:var(--admin-text)] text-xs">Disposición de los enlaces</label>
                    <select name="front_nav_layout" class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] focus:border-[color:var(--admin-accent)] rounded-lg px-3 py-2 text-[color:var(--admin-text)] text-xs outline-none transition-colors">
                        <option value="inline" @selected($navLayout === 'inline')>En línea (horizontal)</option>
                        <option value="stacked" @selected($navLayout === 'stacked')>Apilados (vertical)</option>
                        <option value="dropdown" @selected($navLayout === 'dropdown')>Desplegable (tras el icono)</option>
                    </select>
                </div>

                <div class="space-y-1.5">
                    <label class="admin-field-label font-bold text-[color:var(--admin-text)] text-xs">Icono para mostrar/ocultar el menú</label>
                    <select name="front_nav_toggle_icon" class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] focus:border-[color:var(--admin-accent)] rounded-lg px-3 py-2 text-[color:var(--admin-text)] text-xs outline-none transition-colors">
                        <option value="bars" @selected($navToggleIcon === 'bars')>Barras (hamburguesa)</option>
                        <option value="chevron" @selected($navToggleIcon === 'chevron')>Chevron</option>
                        <option value="plus" @selected($navToggleIcon === 'plus')>Más (+)</option>
                        <option value="grid" @selected($navToggleIcon === 'grid')>Rejilla</option>
                        <option value="dots" @selected($navToggleIcon === 'dots')>Puntos suspensivos</option>
                    </select>
                </div>

                <label class="flex items-start gap-3 p-3 rounded-lg bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] cursor-pointer">
                    <input type="checkbox" name="front_nav_toggle_enabled" value="1"
                           class="mt-0.5 rounded bg-[color:var(--admin-surface)] border-[color:var(--admin-border)] text-[color:var(--admin-accent)] focus:ring-[color:var(--admin-accent)]"
                           {{ $navToggleEnabled ? 'checked' : '' }}>
                    <span>
                        <span class="block font-bold text-[color:var(--admin-text)] text-xs">Mostrar icono de plegado</span>
                        <span class="block text-[11px] text-[color:var(--admin-muted)]">Botón que muestra u oculta el menú de navegación.</span>
                    </span>
                </label>

                <label class="flex items-start gap-3 p-3 rounded-lg bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] cursor-pointer">
                    <input type="checkbox" name="front_nav_toggle_desktop" value="1"
                           class="mt-0.5 rounded bg-[color:var(--admin-surface)] border-[color:var(--admin-border)] text-[color:var(--admin-accent)] focus:ring-[color:var(--admin-accent)]"
                           {{ $navToggleDesktop ? 'checked' : '' }}>
                    <span>
                        <span class="block font-bold text-[color:var(--admin-text)] text-xs">Permitir plegado en escritorio</span>
                        <span class="block text-[11px] text-[color:var(--admin-muted)]">Si está desactivado, el icono solo aparece en móvil.</span>
                    </span>
                </label>
            </div>
        </x-admin.form-card>

        <!-- Sticky Submit Footer -->
        <x-admin.form-card class="flex items-center justify-between gap-4">
            <div class="text-[color:var(--admin-muted)] text-xs">
                Los cambios se aplican de forma inmediata en todo el sitio web.
            </div>
            <div class="flex items-center gap-3">
                <x-admin.button variant="primary" type="submit" class="text-xs flex items-center gap-2">
                    <i class="fa-solid fa-floppy-disk"></i>
                    <span>{{ __('admin.settings.save') }}</span>
                </x-admin.button>
            </div>
        </x-admin.form-card>
    </form>
</div>

<script nonce="{{ $cspNonce ?? '' }}">
function siteSettings() {
    return {
        activeTab: 'general',
        media: {
            site_logo: @js(old('general_site_logo', $settings['general.site_logo'] ?? '')),
            site_logo_dark: @js(old('general_site_logo_dark', $settings['general.site_logo_dark'] ?? '')),
            site_favicon: @js(old('general_site_favicon', $settings['general.site_favicon'] ?? '')),
            og_image: @js(old('general_og_image', $settings['general.og_image'] ?? '')),
        },

        openMedia(field) {
            const target = 'settings-' + field;

            window.addEventListener('file-selected', (event) => {
                if (event.detail?.target === target && event.detail?.file?.url) {
                    this.media[field] = event.detail.file.url;
                }
            }, { once: true });

            window.dispatchEvent(new CustomEvent('open-filemanager', { detail: { target } }));
        },

        clearMedia(field) {
            this.media[field] = '';
        }
    };
}
</script>
@endsection
