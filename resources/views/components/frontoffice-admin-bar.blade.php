@if($canAccessBackoffice)
<!-- Frontoffice Backoffice Direct Bar -->
@if(!empty($adminBarCssUrl))
    <link rel="stylesheet" href="{{ $adminBarCssUrl }}">
@endif
<div id="cms-frontoffice-admin-bar-root"
     x-data="{
        showSeoDrawer: false,
        isCollapsed: false,
        quickEditActive: true,
        init() {
            var savedMode = localStorage.getItem('cms_click_to_edit');
            this.quickEditActive = savedMode !== 'false';
            this.applyQuickEditState();
            document.body.classList.add('has-cms-admin-bar');
        },
        toggleQuickEdit() {
            this.quickEditActive = !this.quickEditActive;
            localStorage.setItem('cms_click_to_edit', this.quickEditActive ? 'true' : 'false');
            this.applyQuickEditState();
        },
        applyQuickEditState() {
            document.body.classList.toggle('cms-edit-mode-active', this.quickEditActive);
        }
     }"
     class="select-none font-mono text-xs admin-bar-root">

    <!-- Top Fixed Bar -->
    <div x-show="!isCollapsed"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="-translate-y-full"
         x-transition:enter-end="translate-y-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="translate-y-0"
         x-transition:leave-end="-translate-y-full"
         class="fixed top-0 left-0 right-0 z-[9999] h-11 bg-[#121212]/95 border-b border-[#00FF99]/20 text-white shadow-[0_4px_25px_rgba(0,0,0,0.85)] px-3 sm:px-5 flex items-center justify-between backdrop-blur-md admin-bar-surface">

        <!-- Left Group: Brand & Direct Content Edit -->
        <div class="flex items-center gap-2 sm:gap-3 h-full min-w-0 flex-1 overflow-hidden py-0">
            <a href="{{ route('admin.dashboard') }}"
               class="flex items-center gap-2 min-w-0 font-mono font-bold text-white hover:opacity-90 transition-opacity admin-bar-brand">
                @if($brandLogo)
                    <img src="{{ $brandLogo }}" alt="{{ $brandAlt }}" class="admin-bar-brand-logo h-5 w-auto max-w-[110px] sm:max-w-[150px] object-contain shrink-0">
                @else
                    <span class="text-sm sm:text-base tracking-tight flex items-center font-bold min-w-0">
                        <span class="text-white">&gt;</span><span class="cursor-blink text-[#00FF99]">_</span><span class="text-white font-black truncate">{{ $cmsBrandName }}</span>
                    </span>
                @endif
                <span class="text-[9px] font-mono text-[#00FF99] border border-[#00FF99]/40 bg-[#00FF99]/10 px-1.5 py-0.5 rounded font-bold uppercase tracking-wider hidden xl:inline admin-badge-role shrink-0">
                    Backoffice
                </span>
            </a>

            <div class="h-4 w-[1px] bg-gray-800 hidden sm:block admin-bar-divider"></div>

            <!-- Direct Content Edit Link -->
            @if($editUrl)
                <a href="{{ $editUrl }}"
                   id="frontoffice-bar-edit-link"
                   class="inline-flex items-center gap-1.5 min-w-0 bg-[#1A1A1A] hover:bg-[#00FF99] text-[#00FF99] hover:text-black border border-[#00FF99]/40 hover:shadow-[0_0_15px_rgba(0,255,153,0.35)] px-2.5 py-1 rounded text-xs font-bold transition-all shrink admin-bar-btn-primary">
                    <i class="fa-solid fa-pen-to-square text-xs shrink-0"></i>
                    <span class="truncate">{{ $editLabel }}</span>
                </a>
            @endif

            <!-- Filter landing: index this page in Google (opens the backoffice pre-filled) -->
            @if(! empty($filterCurationUrl) && Route::has('admin.seo.filters.index'))
                <a href="{{ $filterCurationUrl }}"
                   id="frontoffice-bar-google-link"
                   class="inline-flex items-center gap-1.5 min-w-0 bg-[#1A1A1A] hover:bg-white text-white border border-gray-700 hover:text-gray-900 px-2.5 py-1 rounded text-xs font-bold transition-all shrink admin-bar-btn-primary"
                   title="{{ __('admin.front_bar.tooltip_index_in_google') }}">
                    <i class="fa-brands fa-google text-xs shrink-0"></i>
                    <span class="truncate">
                        {{ ! empty($filterIsIndexable) ? __('admin.front_bar.edit_in_google') : __('admin.front_bar.index_in_google') }}
                    </span>
                </a>
            @endif
        </div>

        <!-- Center Group: Interactive Click-to-Edit Mode Toggle -->
        <div class="hidden lg:flex items-center gap-2">
            <button type="button"
                    @click="toggleQuickEdit()"
                    :class="quickEditActive ? 'bg-[#00FF99]/15 text-[#00FF99] border-[#00FF99]/60 shadow-[0_0_12px_rgba(0,255,153,0.2)] admin-bar-quickedit-active' : 'bg-[#1A1A1A] text-gray-400 border-gray-800 hover:border-gray-700 admin-bar-quickedit-inactive'"
                    class="px-2.5 py-1 rounded-full border flex items-center gap-1.5 text-[11px] transition-all hover:brightness-105 cursor-pointer admin-bar-quickedit-btn"
                    title="{{ __('admin.front_bar.tooltip_edit_blocks') }}">
                <span class="w-2 h-2 rounded-full transition-colors" :class="quickEditActive ? 'bg-[#00FF99] animate-pulse admin-pulse-dot' : 'bg-gray-400'"></span>
                <span>{{ __('admin.front_bar.click_to_edit') }} <strong x-text="quickEditActive ? 'ACTIVO' : 'PAUSADO'" class="tracking-wide"></strong></span>
            </button>
        </div>

        <!-- Right Group: SEO/GEO Score, User Badge & Logout -->
        <div class="flex items-center gap-2 sm:gap-3 shrink-0">
            <!-- Real-Time SEO & GEO Quality Score Button -->
            <button type="button"
                    @click="showSeoDrawer = true"
                    id="frontoffice-bar-seo-score-btn"
                    class="px-2.5 py-1 rounded-full border border-gray-800 bg-[#1A1A1A] hover:border-[#00FF99] text-white hover:text-[#00FF99] inline-flex items-center gap-1.5 text-[11px] font-bold shadow-sm transition-all hover:scale-[1.03] cursor-pointer admin-bar-score-btn"
                    title="{{ __('admin.front_bar.tooltip_seo_audit') }}">
                <span class="w-2 h-2 rounded-full {{ $scoreDotColor }}"></span>
                <span class="hidden sm:inline"><i class="fa-solid fa-bolt text-[#00FF99] admin-bar-accent"></i> {{ __('admin.front_bar.seo_geo') }} <strong>{{ $score }}%</strong></span>
                <span class="sm:hidden font-mono text-[10px]"><i class="fa-solid fa-bolt text-[#00FF99] admin-bar-accent"></i> {{ $score }}%</span>
            </button>

            @stack('front_admin_status')

            <!-- User Info Badge -->
            <div class="hidden xl:flex items-center gap-1.5 text-gray-300 admin-bar-user">
                <span class="font-medium text-white admin-bar-user-name">{{ $currentUser->name }}</span>
                <span class="bg-[#00FF99]/10 text-[#00FF99] border border-[#00FF99]/30 text-[9px] px-1.5 py-0.5 rounded font-bold uppercase admin-badge-role">{{ $currentRoleName }}</span>
            </div>

            <!-- Logout -->
            <form action="{{ route('admin.logout') }}" method="POST" class="inline">
                @csrf
                <button type="submit"
                        class="text-gray-400 hover:text-red-400 text-xs px-2 min-h-[2rem] rounded transition-colors admin-bar-logout"
                        title="{{ __('admin.front_bar.tooltip_logout') }}">
                    Salir
                </button>
            </form>

            <!-- Minimize Button -->
            <button type="button"
                    @click="isCollapsed = true; document.body.classList.remove('has-cms-admin-bar');"
                    class="text-gray-500 hover:text-[#00FF99] p-1.5 min-h-[2rem] min-w-[2rem] inline-flex items-center justify-center text-sm font-bold leading-none admin-bar-minimize"
                    title="{{ __('admin.front_bar.tooltip_minimize') }}">
                &times;
            </button>
        </div>
    </div>

    <!-- Collapsed Floating Trigger (when minimized) -->
    <div x-show="isCollapsed"
         style="display: none;"
         class="fixed top-3 right-3 z-[9999]">
        <button type="button"
                @click="isCollapsed = false; document.body.classList.add('has-cms-admin-bar');"
                class="bg-[#121212]/95 hover:bg-[#1A1A1A] border border-[#00FF99]/40 hover:border-[#00FF99] text-white shadow-[0_0_20px_rgba(0,0,0,0.8)] px-3 py-1.5 rounded-full backdrop-blur-md text-xs flex items-center gap-2 hover:scale-105 transition-all cursor-pointer admin-bar-collapsed-btn">
            <span class="w-2 h-2 rounded-full bg-[#00FF99] animate-ping admin-pulse-dot"></span>
            @if($brandIcon)
                <img src="{{ $brandIcon }}" alt="{{ $brandAlt }}" class="h-4 w-4 rounded object-contain">
            @else
                <span class="font-bold text-white">&gt;<span class="text-[#00FF99] admin-bar-accent">_</span>{{ $cmsBrandName }}</span>
            @endif
            <span class="text-gray-300 admin-text-muted"><i class="fa-solid fa-bolt text-[#00FF99] admin-bar-accent"></i> {{ $score }}%</span>
            <span class="text-[#00FF99] admin-bar-accent">▾</span>
        </button>
    </div>

    <!-- SEO & GEO Real-Time Slide-Over Drawer / Modal -->
    <div x-show="showSeoDrawer"
         @keydown.escape.window="showSeoDrawer = false"
         style="display: none;"
         class="fixed inset-0 z-[10000] overflow-hidden font-mono admin-bar-drawer-root"
         aria-labelledby="slide-over-title" role="dialog" aria-modal="true">
        
        <!-- Backdrop -->
        <div x-show="showSeoDrawer"
             x-transition:enter="ease-in-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in-out duration-300"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             @click="showSeoDrawer = false"
             class="fixed inset-0 bg-black/40 backdrop-blur-md transition-opacity admin-bar-backdrop"></div>

        <div class="fixed inset-y-0 right-0 max-w-full flex pl-10">
            <div x-show="showSeoDrawer"
                 x-transition:enter="transform transition ease-in-out duration-300"
                 x-transition:enter-start="translate-x-full"
                 x-transition:enter-end="translate-x-0"
                 x-transition:leave="transform transition ease-in-out duration-300"
                 x-transition:leave-start="translate-x-0"
                 x-transition:leave-end="translate-x-full"
                 class="w-screen max-w-md md:max-w-lg bg-[#141414] border-l border-gray-800 text-gray-200 shadow-2xl flex flex-col justify-between admin-bar-drawer">

                <!-- Drawer Header -->
                <div class="p-5 border-b border-gray-800 bg-[#121212] admin-card-header flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-[#00FF99]/10 border border-[#00FF99]/40 text-[#00FF99] admin-icon-box flex items-center justify-center font-bold">
                            <i class="fa-solid fa-bolt text-sm"></i>
                        </div>
                        <div>
                            <h2 class="text-sm font-bold text-white font-mono tracking-wide admin-modal-title" id="slide-over-title">
                                Auditoría SEO & GEO en Tiempo Real
                            </h2>
                            <p class="text-[11px] text-gray-400 font-mono truncate max-w-xs admin-text-muted">
                                {{ $contentTitle }}
                            </p>
                        </div>
                    </div>

                    <button type="button" @click="showSeoDrawer = false"
                            class="text-gray-400 hover:text-white admin-btn-close text-2xl font-bold p-1 leading-none">&times;</button>
                </div>

                <!-- Drawer Content -->
                <div class="p-5 overflow-y-auto space-y-6 flex-1 text-gray-300 font-mono admin-text-muted text-xs">
                    <!-- Global Score Card -->
                    <div class="p-4 rounded-xl bg-[#1A1A1A] border border-gray-800 admin-card flex items-center justify-between">
                        <div class="space-y-1">
                            <span class="text-[10px] uppercase font-bold tracking-wider text-gray-400 font-mono admin-text-muted">{{ __('admin.front_bar.global_index') }}</span>
                            <div class="text-2xl font-black text-white font-mono admin-text-primary flex items-baseline gap-1">
                                <span class="{{ $score >= 80 ? 'text-[#00FF99]' : ($score >= 60 ? 'text-amber-500' : 'text-rose-500') }} admin-score-val">{{ $score }}</span>
                                <span class="text-xs text-gray-500 font-normal admin-text-muted">/ 100</span>
                            </div>
                            <p class="text-[11px] text-gray-400 admin-text-muted">
                                {{ __('admin.front_bar.heuristic_notice') }}
                            </p>
                        </div>

                        <div class="flex flex-col gap-1.5 text-right text-[11px] font-mono">
                            <div class="px-2.5 py-1 rounded bg-[#121212] border border-gray-800 admin-stat-box">
                                <span class="text-gray-400 admin-text-muted">{{ __('admin.front_bar.seo_traditional') }}</span>
                                <strong class="{{ $auditResult['seo_score'] >= 80 ? 'text-[#00FF99]' : 'text-amber-500' }} admin-text-accent">{{ $auditResult['seo_score'] }}/100</strong>
                            </div>
                            <div class="px-2.5 py-1 rounded bg-[#121212] border border-gray-800 admin-stat-box">
                                <span class="text-gray-400 admin-text-muted">{{ __('admin.front_bar.geo_citability_colon') }}</span>
                                <strong class="{{ $auditResult['geo_score'] >= 80 ? 'text-[#00FF99]' : 'text-amber-500' }} admin-text-accent">{{ $auditResult['geo_score'] }}/100</strong>
                            </div>
                        </div>
                    </div>

                    <!-- Dimension Breakdown (SEO vs GEO) -->
                    <div class="grid grid-cols-2 gap-3">
                        <div class="p-3.5 rounded-lg bg-[#181818] border border-gray-800 admin-stat-box space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-white admin-text-primary text-[11px]"><i class="fa-solid fa-magnifying-glass mr-1.5"></i>{{ __('admin.front_bar.seo_classic') }}</span>
                                <span class="text-[#00FF99] font-mono admin-text-accent font-bold">{{ $auditResult['seo_score'] }}/100</span>
                            </div>
                            <ul class="text-[11px] text-gray-400 font-mono admin-text-muted space-y-1">
                                <li>{{ __('admin.front_bar.title_metric') }} <strong class="text-gray-200 admin-text-primary">{{ $auditResult['metrics']['title_length'] }} car.</strong></li>
                                <li>{{ __('admin.front_bar.meta_desc') }} <strong class="text-gray-200 admin-text-primary">{{ $auditResult['metrics']['meta_length'] }} car.</strong></li>
                                <li>{{ __('admin.front_bar.subtitles') }} <strong class="text-gray-200 admin-text-primary">{{ $auditResult['metrics']['h2_subheadings'] }} H2 / {{ $auditResult['metrics']['h3_subheadings'] }} H3</strong></li>
                            </ul>
                        </div>

                        <div class="p-3.5 rounded-lg bg-[#181818] border border-gray-800 admin-stat-box space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-white admin-text-primary text-[11px]"><i class="fa-solid fa-robot mr-1.5"></i>{{ __('admin.front_bar.geo_citability') }}</span>
                                <span class="text-[#00FF99] font-mono admin-text-accent font-bold">{{ $auditResult['geo_score'] }}/100</span>
                            </div>
                            <ul class="text-[11px] text-gray-400 font-mono admin-text-muted space-y-1">
                                <li>{{ __('admin.front_bar.words') }} <strong class="text-gray-200 admin-text-primary">{{ $auditResult['metrics']['word_count'] }}</strong></li>
                                <li>{{ __('admin.front_bar.density') }} <strong class="text-gray-200 admin-text-primary">~{{ $auditResult['metrics']['avg_words_per_sentence'] }} p/frase</strong></li>
                                <li>{{ __('admin.front_bar.ingestion') }} <strong class="text-[#00FF99] admin-text-accent">{{ __('admin.front_bar.llms_ok') }}</strong></li>
                            </ul>
                        </div>
                    </div>

                    <!-- Actionable Recommendations & Tips to Improve -->
                    <div class="space-y-3">
                        <div class="flex items-center justify-between border-b border-gray-800 pb-1.5">
                            <h3 class="font-bold text-white admin-text-primary text-xs flex items-center gap-1.5">
                                <i class="fa-solid fa-lightbulb text-amber-400"></i>
                                <span>{{ __('admin.front_bar.tips_title') }}</span>
                            </h3>
                            <span class="text-[10px] text-gray-400 font-mono admin-text-muted">{{ count($auditResult['recommendations']) }} sugerencias</span>
                        </div>

                        @if(!empty($auditResult['recommendations']))
                            <div class="space-y-2.5">
                                @foreach($auditResult['recommendations'] as $tip)
                                    <div class="p-3 rounded-lg admin-alert admin-alert-warning text-xs leading-relaxed flex items-start gap-2.5">
                                        <i class="fa-solid fa-triangle-exclamation shrink-0 mt-0.5"></i>
                                        <div>
                                            <p class="font-medium">{{ $tip }}</p>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="p-4 rounded-lg admin-alert admin-alert-success text-xs text-center space-y-1">
                                <p class="font-bold text-sm"><i class="fa-solid fa-circle-check mr-1.5"></i>{{ __('admin.front_bar.excellent_title') }}</p>
                                <p class="text-[11px] admin-text-muted">{{ __('admin.front_bar.excellent_desc') }}</p>
                            </div>
                        @endif
                    </div>

                    <!-- Direct Backoffice Editor Call to Action -->
                    @if($editUrl)
                        <div class="p-4 rounded-xl bg-[#1A1A1A] border border-[#00FF99]/30 admin-card space-y-3">
                            <div class="space-y-1">
                                <h4 class="font-bold text-white admin-text-primary text-xs">{{ __('admin.front_bar.apply_in_backoffice') }}</h4>
                                <p class="text-[11px] text-gray-400 admin-text-muted">{{ __('admin.front_bar.tips_desc') }}</p>
                            </div>

                            <a href="{{ $editUrl }}"
                               class="w-full text-center py-2 px-3 bg-[#121212] hover:bg-[#00FF99] text-[#00FF99] hover:text-black border border-[#00FF99] admin-btn-primary rounded-lg font-bold text-xs flex items-center justify-center gap-1.5 shadow-md transition-all">
                                <i class="fa-solid fa-pen-to-square"></i>
                                <span>{{ $editLabel }} en Backoffice</span>
                                <span>&rarr;</span>
                            </a>
                        </div>
                    @endif
                </div>

                <!-- Drawer Footer -->
                <div class="p-4 border-t border-gray-800 bg-[#121212] font-mono admin-card-footer flex items-center justify-between text-xs">
                    <div class="flex items-center gap-4">
                        <a href="{{ route('admin.seo.audit') }}" class="text-[#00FF99] hover:underline admin-text-accent flex items-center gap-1 font-semibold">
                            <i class="fa-solid fa-chart-line"></i>
                            <span>Auditoría Global &rarr;</span>
                        </a>
                        @stack('front_admin_actions')
                    </div>
                    <button type="button" @click="showSeoDrawer = false" class="text-gray-400 hover:text-white admin-btn-secondary">
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Scoped Styles & Script for Interactive Click-to-Edit Mode -->
<style>
    /* Body top offset when admin bar is present */
    :root {
        --admin-bar-height: 44px;
    }

    body.has-cms-admin-bar {
        padding-top: var(--admin-bar-height) !important;
    }

    /* Sticky theme headers must stick BELOW the fixed admin bar, otherwise the
       theme logo slides under the bar while scrolling. */
    body.has-cms-admin-bar header.sticky {
        top: var(--admin-bar-height) !important;
    }

    /* ======================================================================
       Admin bar design tokens (dbCMS defaults).
       The bar chrome is driven entirely by these variables, so it follows the
       active backoffice theme. A theme may ship `assets/admin-bar.css` (loaded
       above when present) that redefines them on its own `.theme-{id}` class.
       Tokens also style the quick-edit pill injected into the page, which lives
       outside the bar subtree — hence they are declared on :root.
       ====================================================================== */
    :root {
        --admin-bar-bg: rgba(18, 18, 18, 0.95);
        --admin-bar-panel: #1A1A1A;
        --admin-bar-panel-alt: #181818;
        --admin-bar-border: rgba(0, 255, 153, 0.20);
        --admin-bar-border-soft: #1F2937;
        --admin-bar-border-strong: #00FF99;
        --admin-bar-text: #FFFFFF;
        --admin-bar-muted: #9CA3AF;
        --admin-bar-accent: #00FF99;
        --admin-bar-accent-hover: #00FF99;
        --admin-bar-accent-contrast: #121212;
        --admin-bar-chip-bg: rgba(0, 255, 153, 0.10);
        --admin-bar-chip-border: rgba(0, 255, 153, 0.30);
        --admin-bar-hover-bg: #1A1A1A;
        --admin-bar-glow: rgba(0, 255, 153, 0.35);
        --admin-bar-pill-bg: #121212;
        --admin-bar-pill-text: #00FF99;
        --admin-bar-pill-border: #00FF99;
        --admin-bar-pill-hover-bg: #00FF99;
        --admin-bar-pill-hover-text: #121212;
        --admin-bar-font: 'Google Sans Code', ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        --admin-bar-shadow: 0 4px 25px rgba(0, 0, 0, 0.85);
    }

    /* ------------------------------------------------------------------ */
    /* Top bar                                                             */
    /* ------------------------------------------------------------------ */
    .admin-bar-root {
        font-family: var(--admin-bar-font) !important;
    }

    .admin-bar-surface {
        background-color: var(--admin-bar-bg) !important;
        border-bottom-color: var(--admin-bar-border) !important;
        color: var(--admin-bar-text) !important;
        font-family: var(--admin-bar-font) !important;
        box-shadow: var(--admin-bar-shadow) !important;
    }
    .admin-bar-surface .text-white,
    .admin-bar-surface .text-gray-200,
    .admin-bar-surface .text-gray-300,
    .admin-bar-surface .text-gray-500 {
        color: var(--admin-bar-text) !important;
    }
    .admin-bar-surface .text-gray-400 {
        color: var(--admin-bar-muted) !important;
    }

    .admin-bar-brand {
        color: var(--admin-bar-text) !important;
    }
    .admin-bar-divider {
        background-color: var(--admin-bar-border-soft) !important;
    }
    .admin-bar-accent {
        color: var(--admin-bar-accent) !important;
    }

    .admin-badge-role {
        background-color: var(--admin-bar-chip-bg) !important;
        border-color: var(--admin-bar-chip-border) !important;
        color: var(--admin-bar-accent) !important;
    }

    .admin-bar-btn-primary {
        background-color: var(--admin-bar-panel) !important;
        border-color: var(--admin-bar-border-strong) !important;
        color: var(--admin-bar-accent) !important;
    }
    /* El hover debe mantener contraste fondo/texto: antes heredaba utilidades
       (hover:bg-[#00FF99] hover:text-black) y en temas con acento claro el
       botón quedaba acento sobre acento y desaparecía. */
    .admin-bar-root .admin-bar-btn-primary:hover,
    .admin-bar-root .admin-bar-btn-primary:focus-visible {
        background-color: var(--admin-bar-accent) !important;
        border-color: var(--admin-bar-accent) !important;
        color: var(--admin-bar-accent-contrast) !important;
        box-shadow: 0 0 15px var(--admin-bar-glow) !important;
    }
    .admin-bar-root .admin-bar-btn-primary:hover i,
    .admin-bar-root .admin-bar-btn-primary:hover span,
    .admin-bar-root .admin-bar-btn-primary:focus-visible i,
    .admin-bar-root .admin-bar-btn-primary:focus-visible span {
        color: inherit !important;
    }

    .admin-bar-quickedit-btn {
        background-color: var(--admin-bar-panel) !important;
        border-color: var(--admin-bar-border-soft) !important;
        color: var(--admin-bar-muted) !important;
    }
    .admin-bar-quickedit-btn:hover {
        background-color: var(--admin-bar-hover-bg) !important;
        border-color: var(--admin-bar-border-strong) !important;
        color: var(--admin-bar-accent) !important;
    }
    .admin-bar-quickedit-active {
        background-color: var(--admin-bar-chip-bg) !important;
        border-color: var(--admin-bar-border-strong) !important;
        color: var(--admin-bar-accent) !important;
        box-shadow: 0 0 12px var(--admin-bar-glow) !important;
    }
    .admin-bar-quickedit-active:hover {
        background-color: var(--admin-bar-accent) !important;
        color: var(--admin-bar-accent-contrast) !important;
    }
    .admin-pulse-dot {
        background-color: var(--admin-bar-accent) !important;
    }

    .admin-bar-score-btn {
        background-color: var(--admin-bar-panel) !important;
        border-color: var(--admin-bar-border-soft) !important;
        color: var(--admin-bar-text) !important;
    }
    .admin-bar-score-btn:hover {
        border-color: var(--admin-bar-accent) !important;
        color: var(--admin-bar-accent) !important;
    }

    .admin-bar-user {
        color: var(--admin-bar-muted) !important;
    }
    .admin-bar-user-name {
        color: var(--admin-bar-text) !important;
    }
    .admin-bar-logout {
        color: var(--admin-bar-muted) !important;
    }
    .admin-bar-logout:hover {
        color: #F43F5E !important;
    }
    .admin-bar-minimize {
        color: var(--admin-bar-muted) !important;
    }
    .admin-bar-minimize:hover {
        color: var(--admin-bar-accent) !important;
    }

    .admin-bar-collapsed-btn {
        background-color: var(--admin-bar-bg) !important;
        border-color: var(--admin-bar-border-strong) !important;
        color: var(--admin-bar-text) !important;
        font-family: var(--admin-bar-font) !important;
    }
    .admin-bar-collapsed-btn:hover {
        border-color: var(--admin-bar-accent) !important;
    }
    .admin-bar-collapsed-btn .text-white {
        color: var(--admin-bar-text) !important;
    }
    .admin-text-muted {
        color: var(--admin-bar-muted) !important;
    }

    /* ------------------------------------------------------------------ */
    /* SEO / GEO drawer                                                    */
    /* ------------------------------------------------------------------ */
    .admin-bar-drawer {
        background-color: var(--admin-bar-panel) !important;
        border-left-color: var(--admin-bar-border-soft) !important;
        color: var(--admin-bar-text) !important;
        font-family: var(--admin-bar-font) !important;
    }
    .admin-card-header,
    .admin-card-footer {
        background-color: var(--admin-bar-bg) !important;
        border-color: var(--admin-bar-border-soft) !important;
        color: var(--admin-bar-text) !important;
    }
    .admin-card,
    .admin-stat-box {
        background-color: var(--admin-bar-panel) !important;
        border-color: var(--admin-bar-border-soft) !important;
        color: var(--admin-bar-text) !important;
    }
    .admin-icon-box {
        background-color: var(--admin-bar-chip-bg) !important;
        border-color: var(--admin-bar-chip-border) !important;
        color: var(--admin-bar-accent) !important;
    }
    .admin-modal-title,
    .admin-text-primary {
        color: var(--admin-bar-text) !important;
    }
    .admin-text-accent {
        color: var(--admin-bar-accent) !important;
    }
    .admin-alert-success {
        background-color: var(--admin-bar-chip-bg) !important;
        border-color: var(--admin-bar-chip-border) !important;
        color: var(--admin-bar-accent) !important;
    }
    .admin-btn-close,
    .admin-btn-secondary {
        color: var(--admin-bar-muted) !important;
    }
    .admin-btn-close:hover,
    .admin-btn-secondary:hover {
        color: var(--admin-bar-text) !important;
    }
    .admin-bar-drawer .border-gray-800 {
        border-color: var(--admin-bar-border-soft) !important;
    }

    /* Drawer CTA reuses the short `admin-btn-primary` alias (vs `admin-bar-btn-primary`) */
    .admin-btn-primary {
        background-color: var(--admin-bar-panel) !important;
        border-color: var(--admin-bar-border-strong) !important;
        color: var(--admin-bar-accent) !important;
    }
    .admin-btn-primary:hover {
        background-color: var(--admin-bar-accent) !important;
        border-color: var(--admin-bar-accent) !important;
        color: var(--admin-bar-accent-contrast) !important;
        box-shadow: 0 0 15px var(--admin-bar-glow) !important;
    }

    /* Accent catch-all: every remaining neon-green utility inside the bar follows
       the active theme accent (dbCMS keeps #00FF99, termoCMS becomes teal). */
    .admin-bar-root .text-\[\#00FF99\] { color: var(--admin-bar-accent) !important; }
    .admin-bar-root .border-\[\#00FF99\] { border-color: var(--admin-bar-accent) !important; }
    .admin-bar-root .bg-\[\#00FF99\] { background-color: var(--admin-bar-accent) !important; }
    .admin-bar-root .hover\:text-\[\#00FF99\]:hover { color: var(--admin-bar-accent) !important; }
    .admin-bar-root .hover\:border-\[\#00FF99\]:hover { border-color: var(--admin-bar-accent) !important; }
    .admin-bar-root .hover\:bg-\[\#00FF99\]:hover {
        background-color: var(--admin-bar-accent) !important;
        color: var(--admin-bar-accent-contrast) !important;
    }

    /* Typography: the bar always uses the active theme font, never dbCMS mono. */
    .admin-bar-root .font-mono { font-family: var(--admin-bar-font) !important; }

    /* ------------------------------------------------------------------ */
    /* Semantic surfaces & alerts                                          */
    /*                                                                     */
    /* The panel is injected over the FRONT themes, where the backoffice    */
    /* dark palette does not apply (nor does termocms.css). Every surface   */
    /* therefore paints itself with the `--admin-bar-*` tokens so the text  */
    /* stays legible on both the dark dbCMS bar and the light termoCMS one. */
    /* ------------------------------------------------------------------ */
    .admin-card,
    .admin-stat-box {
        background-color: var(--admin-bar-panel);
        border-color: var(--admin-bar-border);
        color: var(--admin-bar-text);
    }

    .admin-card-header {
        background-color: var(--admin-bar-panel-alt);
        border-color: var(--admin-bar-border);
        color: var(--admin-bar-text);
    }

    .admin-card-footer {
        background-color: var(--admin-bar-panel-alt);
        border-color: var(--admin-bar-border);
        color: var(--admin-bar-muted);
    }

    .admin-text-primary { color: var(--admin-bar-text) !important; }
    .admin-text-muted { color: var(--admin-bar-muted) !important; }
    .admin-text-accent { color: var(--admin-bar-accent) !important; }
    .admin-score-val { color: var(--admin-bar-accent) !important; }

    .admin-icon-box {
        background-color: var(--admin-bar-chip-bg) !important;
        border-color: var(--admin-bar-chip-border) !important;
        color: var(--admin-bar-accent) !important;
    }

    .admin-modal-title { color: var(--admin-bar-text) !important; }

    .admin-pulse-dot {
        background-color: var(--admin-bar-accent) !important;
        box-shadow: 0 0 10px var(--admin-bar-glow) !important;
    }

    .admin-badge-role,
    .admin-badge-maintenance {
        background-color: var(--admin-bar-chip-bg) !important;
        border-color: var(--admin-bar-chip-border) !important;
        color: var(--admin-bar-accent) !important;
    }

    .admin-btn-primary {
        background-color: var(--admin-bar-accent) !important;
        border-color: var(--admin-bar-accent) !important;
        color: var(--admin-bar-accent-contrast) !important;
    }

    .admin-btn-secondary,
    .admin-btn-close {
        background-color: var(--admin-bar-panel-alt) !important;
        border-color: var(--admin-bar-border) !important;
        color: var(--admin-bar-text) !important;
    }

    .admin-btn-secondary:hover,
    .admin-btn-close:hover {
        background-color: var(--admin-bar-hover-bg) !important;
        color: var(--admin-bar-accent) !important;
    }

    /* Alertas: los bloques ámbar/verde/rojo del auditor SEO y GEO.        */
    /* Sin esto el texto ámbar claro quedaba sobre fondo ámbar claro.      */
    .admin-bar-root .admin-alert,
    .admin-bar-root .admin-alert-warning {
        background-color: var(--admin-bar-alert-warning-bg) !important;
        border-color: var(--admin-bar-alert-warning-border) !important;
        color: var(--admin-bar-alert-warning-text) !important;
    }

    .admin-bar-root .admin-alert-warning i { color: var(--admin-bar-alert-warning-icon) !important; }

    .admin-bar-root .admin-alert-success {
        background-color: var(--admin-bar-alert-success-bg) !important;
        border-color: var(--admin-bar-alert-success-border) !important;
        color: var(--admin-bar-alert-success-text) !important;
    }

    .admin-bar-root .admin-alert-danger {
        background-color: var(--admin-bar-alert-danger-bg) !important;
        border-color: var(--admin-bar-alert-danger-border) !important;
        color: var(--admin-bar-alert-danger-text) !important;
    }

    /* Amber warning surfaces rendered with utilities inside the bar.      */
    .admin-bar-root .bg-amber-500\/10,
    .admin-bar-root [class*="bg-amber-500/"] {
        background-color: var(--admin-bar-alert-warning-bg) !important;
        border-color: var(--admin-bar-alert-warning-border) !important;
    }
    .admin-bar-root [class*="bg-amber-500/"] { color: var(--admin-bar-alert-warning-text) !important; }
    .admin-bar-root [class*="text-amber-200"],
    .admin-bar-root [class*="text-amber-300"] { color: var(--admin-bar-alert-warning-text) !important; }
    .admin-bar-root [class*="text-amber-400"],
    .admin-bar-root [class*="text-amber-500"] { color: var(--admin-bar-alert-warning-icon) !important; }

    .admin-bar-root [class*="bg-emerald-500/"],
    .admin-bar-root [class*="bg-\[\#00FF99\]\/"] {
        background-color: var(--admin-bar-alert-success-bg) !important;
        border-color: var(--admin-bar-alert-success-border) !important;
        color: var(--admin-bar-alert-success-text) !important;
    }

    .admin-bar-root [class*="bg-rose-500/"],
    .admin-bar-root [class*="bg-red-500/"] {
        background-color: var(--admin-bar-alert-danger-bg) !important;
        border-color: var(--admin-bar-alert-danger-border) !important;
        color: var(--admin-bar-alert-danger-text) !important;
    }

    /* ------------------------------------------------------------------ */
    /* Click-to-edit affordances (rendered in the page, outside the bar)   */
    /* ------------------------------------------------------------------ */
    .cursor-blink {
        color: var(--admin-bar-accent);
        display: inline-block;
        animation: blink 1s step-end infinite;
    }
    @keyframes blink {
        0%, 100% { opacity: 1; }
        50% { opacity: 0; }
    }

    .cms-edit-mode-active [data-cms-edit-url] {
        position: relative;
        cursor: pointer;
        transition: outline 0.2s ease, box-shadow 0.2s ease;
    }
    .cms-edit-mode-active [data-cms-edit-url]:hover {
        outline: 2px dashed var(--admin-bar-accent) !important;
        outline-offset: 4px;
        box-shadow: 0 0 25px var(--admin-bar-glow);
    }

    /* Floating Quick Edit Pill inside highlighted elements */
    .cms-quick-edit-pill {
        display: none;
        position: absolute;
        top: 8px;
        right: 8px;
        z-index: 50;
        background: var(--admin-bar-pill-bg);
        color: var(--admin-bar-pill-text);
        border: 1px solid var(--admin-bar-pill-border);
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 11px;
        font-family: var(--admin-bar-font);
        font-weight: 700;
        text-decoration: none;
        box-shadow: 0 4px 14px rgba(0, 0, 0, 0.8);
        cursor: pointer;
        align-items: center;
        gap: 6px;
        pointer-events: auto;
        transition: all 0.2s ease;
    }
    .cms-quick-edit-pill:hover {
        background: var(--admin-bar-pill-hover-bg);
        color: var(--admin-bar-pill-hover-text);
        transform: translateY(-1px);
        box-shadow: 0 0 15px var(--admin-bar-glow);
    }
    .cms-edit-mode-active [data-cms-edit-url]:hover > .cms-quick-edit-pill {
        display: inline-flex;
    }
</style>

<script nonce="{{ $cspNonce ?? '' }}">
document.addEventListener('DOMContentLoaded', function() {
    var globalEditUrl = @json($editUrl);
    var globalEditLabel = @json($editLabel);

    // Attach Click-to-Edit affordances. Sections carry `data-cms-section`
    // (stable id) so the backoffice opens the exact block, not just the root.
    function initClickToEdit() {
        document.querySelectorAll('[data-cms-section]').forEach(function (el) {
            if (! globalEditUrl) {
                return;
            }

            var sectionId = el.getAttribute('data-cms-section');

            el.setAttribute('data-cms-edit-url', globalEditUrl + (sectionId ? '?section=' + encodeURIComponent(sectionId) : ''));
            el.setAttribute('data-cms-edit-label', @json(__('admin.front_bar.edit_section')));
        });

        var targets = Array.prototype.filter.call(
            document.querySelectorAll('[data-cms-edit-url]'),
            function (el) {
                // Skip entity-level wrappers that already contain per-section
                // targets: each section carries its own precise deep-link pill.
                return el.hasAttribute('data-cms-section') || ! el.querySelector('[data-cms-section]');
            }
        );
        
        // If no explicit elements have data-cms-edit-url, fallback to the main container
        if (targets.length === 0 && globalEditUrl) {
            var mainEl = document.querySelector('main') || document.querySelector('article') || document.querySelector('#content');
            if (mainEl) {
                mainEl.setAttribute('data-cms-edit-url', globalEditUrl);
                mainEl.setAttribute('data-cms-edit-label', globalEditLabel || 'Editar Contenido');
                targets = [mainEl];
            }
        }

        targets.forEach(function(el) {
            var url = el.getAttribute('data-cms-edit-url');
            var label = el.getAttribute('data-cms-edit-label') || 'Editar en Backoffice';

            // Ensure element can anchor absolute pill
            var computedStyle = window.getComputedStyle(el);
            if (computedStyle.position === 'static') {
                el.style.position = 'relative';
            }

            // Create floating pill if not present
            if (!el.querySelector('.cms-quick-edit-pill')) {
                var pill = document.createElement('a');
                pill.href = url;
                pill.className = 'cms-quick-edit-pill';
                pill.innerHTML = '<i class="fa-solid fa-pen-to-square"></i> <span>' + label + '</span>';
                pill.title = 'Haga clic para editar este contenido directamente en el Backoffice';

                pill.addEventListener('click', function(e) {
                    e.stopPropagation();
                });

                el.appendChild(pill);
            }

            // Direct click on content element when Quick Edit mode is active
            el.addEventListener('click', function(e) {
                // If user clicked inside an existing anchor link or form, don't hijack unless Alt/Ctrl key is pressed
                if (e.target.closest('a') && !e.target.closest('.cms-quick-edit-pill')) {
                    if (e.altKey || e.ctrlKey || e.metaKey) {
                        e.preventDefault();
                        window.location.href = url;
                    }
                    return;
                }

                // If clicking directly while in Quick Edit mode and target is near the outline or double-clicked
                if (document.body.classList.contains('cms-edit-mode-active') && (e.altKey || e.shiftKey || e.target.classList.contains('cms-quick-edit-pill'))) {
                    e.preventDefault();
                    window.location.href = url;
                }
            });

            // Double click anywhere on editable element opens backoffice editor
            el.addEventListener('dblclick', function(e) {
                if (document.body.classList.contains('cms-edit-mode-active')) {
                    e.preventDefault();
                    window.location.href = url;
                }
            });
        });
    }

    initClickToEdit();
});
</script>
@endif
