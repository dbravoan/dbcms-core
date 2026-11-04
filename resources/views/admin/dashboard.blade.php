@extends('admin.layout')

@section('title', __('admin.page_titles.dashboard'))

@section('content')
<div class="admin-page space-y-8 sm:space-y-10 pb-12">
    <!-- Header Hero Banner -->
    <x-admin.form-card class="sm:p-7 relative overflow-hidden">
        <div class="absolute -right-20 -top-20 w-72 h-72 bg-[color:var(--admin-accent)]/5 rounded-full blur-3xl pointer-events-none"></div>

        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6 relative z-10">
            <div>
                <div class="flex items-center gap-2 mb-2">
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-mono font-bold bg-[color:var(--admin-accent)]/10 text-[color:var(--admin-accent)] border border-[color:var(--admin-accent)]/30">
                        <span class="w-1.5 h-1.5 rounded-full bg-[color:var(--admin-accent)] animate-pulse"></span>
                        SISTEMA OPERATIVO &bull; v{{ config('cms.version') }}
                    </span>
                </div>
                <h1 class="admin-page-title">
                    <span class="text-[color:var(--admin-accent)] font-mono select-none">&gt;</span> {{ __('admin.dashboard.control_panel') }} <span class="text-[color:var(--admin-accent)]">{{ $cmsBrandName }}</span>
                </h1>
                <p class="text-xs text-[color:var(--admin-muted)] font-mono mt-1.5">
                    {{ __('admin.dashboard.active_session_as') }} <span class="text-[color:var(--admin-text)] font-bold">{{ auth()->user()?->name }}</span>
                    ({{ __('admin.dashboard.role') }}: <span class="text-[color:var(--admin-accent)] font-bold uppercase">{{ auth()->user()?->roles->pluck('name')->join(', ') ?: __('admin.dashboard.no_role') }}</span>)
                </p>
            </div>

            <!-- Header Action Buttons -->
            <div class="flex flex-wrap items-center gap-3">
                @stack('admin_dashboard_actions')
                <x-admin.button variant="primary" type="button"
                        data-dispatch="start-onboarding-tour"
                        class="text-xs py-2.5 px-4 shadow-sm"
                        title="{{ __('admin.dashboard.system_tour_tooltip') }}">
                    <span>&gt;_help</span>
                    <span>{{ __('admin.dashboard.system_tour') }}</span>
                </x-admin.button>
                @unless(app()->isProduction())
                <a href="http://localhost:8088"
                   target="_blank"
                   class="px-4 py-2.5 bg-[color:var(--admin-surface)] hover:bg-[color:var(--admin-surface-raised)] border border-[color:var(--admin-border)] hover:border-[color:var(--admin-border)] text-[color:var(--admin-text)] hover:text-[color:var(--admin-accent)] font-mono font-bold rounded-lg text-xs transition-all flex items-center gap-2 shadow-sm">
                    <i class="fa-solid fa-database"></i>
                    <span>{{ __('admin.layout.adminer') }}</span>
                    <span class="text-[color:var(--admin-muted)]">&nearr;</span>
                </a>
                @endunless
            </div>
        </div>
    </x-admin.form-card>

    <!-- KPI Statistics Grid -->
    <div>
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-xs font-mono font-bold text-[color:var(--admin-muted)] uppercase tracking-wider flex items-center gap-2">
                <span class="text-[color:var(--admin-accent)]">&gt;</span> {{ __('admin.dashboard.metrics') }}
            </h2>
            <span class="text-[11px] font-mono text-[color:var(--admin-muted)]">{{ __('admin.dashboard.realtime') }}</span>
        </div>
        <div class="flex gap-4 sm:gap-6 overflow-x-auto py-2 -my-2">
            <!-- Páginas -->
            @if(!empty($hasPages) && Route::has('admin.pages.index'))
            <a href="{{ route('admin.pages.index') }}" class="admin-card p-5 flex-1 min-w-[170px] sm:min-w-[200px] hover:border-[color:var(--admin-accent)]/40 shadow-sm transition-all duration-200 group flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between text-[color:var(--admin-muted)] mb-2">
                        <span class="text-[11px] font-mono uppercase font-semibold group-hover:text-[color:var(--admin-text)]">{{ __('admin.dashboard.pages') }}</span>
                        <span class="p-1.5 rounded bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] flex items-center justify-center text-[color:var(--admin-accent)]"><i class="fa-solid fa-file-lines text-xs"></i></span>
                    </div>
                    <div class="text-2xl sm:text-3xl font-black text-[color:var(--admin-text)] font-mono group-hover:text-[color:var(--admin-accent)] transition-colors">{{ $stats['total_pages'] }}</div>
                </div>
                <div class="text-[10px] text-[color:var(--admin-accent)] font-mono mt-3 pt-2.5 border-t border-[color:var(--admin-border)]/80 flex items-center justify-between">
                    <span>{{ __('admin.dashboard.pages_hint') }}</span>
                    <span class="text-[color:var(--admin-muted)] group-hover:text-[color:var(--admin-accent)]">&rarr;</span>
                </div>
            </a>
            @endif


@foreach($moduleDashboard as $contribution)
                @if(isset($contribution['views']['metrics']))
                    @include($contribution['views']['metrics'], $contribution['data'])
                @endif
            @endforeach

            @if($canManageModules)
            <!-- Módulos -->
            <a href="{{ route('admin.modules.index') }}" class="admin-card p-5 flex-1 min-w-[170px] sm:min-w-[200px] hover:border-[color:var(--admin-accent)]/40 shadow-sm transition-all duration-200 group flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between text-[color:var(--admin-muted)] mb-2">
                        <span class="text-[11px] font-mono uppercase font-semibold group-hover:text-[color:var(--admin-text)]">{{ __('admin.dashboard.modules') }}</span>
                        <span class="p-1.5 rounded bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] flex items-center justify-center text-[color:var(--admin-accent)]"><i class="fa-solid fa-puzzle-piece text-xs"></i></span>
                    </div>
                    <div class="text-2xl sm:text-3xl font-black text-[color:var(--admin-text)] font-mono group-hover:text-[color:var(--admin-accent)] transition-colors">{{ $stats['installed_modules'] }}</div>
                </div>
                <div class="text-[10px] text-[color:var(--admin-accent)] font-mono mt-3 pt-2.5 border-t border-[color:var(--admin-border)]/80 flex items-center justify-between">
                    <span>{{ __('admin.dashboard.modules_hint') }}</span>
                    <span class="text-[color:var(--admin-muted)] group-hover:text-[color:var(--admin-accent)]">&rarr;</span>
                </div>
            </a>

            <!-- Mercados -->
            <a href="{{ route('admin.markets.index') }}" class="admin-card p-5 flex-1 min-w-[170px] sm:min-w-[200px] hover:border-[color:var(--admin-accent)]/40 shadow-sm transition-all duration-200 group flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between text-[color:var(--admin-muted)] mb-2">
                        <span class="text-[11px] font-mono uppercase font-semibold group-hover:text-[color:var(--admin-text)]">{{ __('admin.dashboard.markets') }}</span>
                        <span class="p-1.5 rounded bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] flex items-center justify-center text-[color:var(--admin-accent)]"><i class="fa-solid fa-globe text-xs"></i></span>
                    </div>
                    <div class="text-2xl sm:text-3xl font-black text-[color:var(--admin-text)] font-mono group-hover:text-[color:var(--admin-accent)] transition-colors">{{ $stats['active_markets'] }}</div>
                </div>
                <div class="text-[10px] text-[color:var(--admin-accent)] font-mono mt-3 pt-2.5 border-t border-[color:var(--admin-border)]/80 flex items-center justify-between">
                    <span>{{ __('admin.dashboard.markets_hint') }}</span>
                    <span class="text-[color:var(--admin-muted)] group-hover:text-[color:var(--admin-accent)]">&rarr;</span>
                </div>
            </a>

            @endif
            @if($canManageUsers)
            <!-- Usuarios -->
            <a href="{{ route('admin.users.index') }}" class="admin-card p-5 flex-1 min-w-[170px] sm:min-w-[200px] hover:border-[color:var(--admin-accent)]/40 shadow-sm transition-all duration-200 group flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between text-[color:var(--admin-muted)] mb-2">
                        <span class="text-[11px] font-mono uppercase font-semibold group-hover:text-[color:var(--admin-text)]">{{ __('admin.dashboard.users') }}</span>
                        <span class="p-1.5 rounded bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] flex items-center justify-center text-[color:var(--admin-accent)]"><i class="fa-solid fa-users text-xs"></i></span>
                    </div>
                    <div class="text-2xl sm:text-3xl font-black text-[color:var(--admin-text)] font-mono group-hover:text-[color:var(--admin-accent)] transition-colors">{{ $stats['total_users'] }}</div>
                </div>
                <div class="text-[10px] text-[color:var(--admin-accent)] font-mono mt-3 pt-2.5 border-t border-[color:var(--admin-border)]/80 flex items-center justify-between">
                    <span>{{ __('admin.dashboard.users_hint') }}</span>
                    <span class="text-[color:var(--admin-muted)] group-hover:text-[color:var(--admin-accent)]">&rarr;</span>
                </div>
            </a>
            @endif
        </div>
    </div>

    <!-- Quick Creation & Action Hub -->
    <div>
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-xs font-mono font-bold text-[color:var(--admin-muted)] uppercase tracking-wider flex items-center gap-2">
                <span class="text-[color:var(--admin-accent)]">&gt;</span> {{ __('admin.dashboard.quick_actions') }}
            </h2>
            <span class="text-[11px] font-mono text-[color:var(--admin-muted)]">{{ __('admin.dashboard.frequent_flows') }}</span>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 sm:gap-6">
            @if(!empty($hasPages) && Route::has('admin.pages.create'))
            <a href="{{ route('admin.pages.create') }}" class="admin-card p-5 hover:border-[color:var(--admin-accent)]/50 hover:bg-[color:var(--admin-surface)] transition-all duration-200 group block">
                <div class="flex items-center justify-between mb-3">
                    <span class="w-10 h-10 rounded-lg bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] group-hover:border-[color:var(--admin-accent)]/40 transition-colors flex items-center justify-center text-[color:var(--admin-accent)] text-base"><i class="fa-solid fa-file-lines"></i></span>
                    <span class="text-[color:var(--admin-muted)] group-hover:text-[color:var(--admin-accent)] text-xs font-mono transition-colors">&rarr;</span>
                </div>
                <h3 class="font-bold text-[color:var(--admin-text)] text-sm group-hover:text-[color:var(--admin-accent)] transition-colors">{{ __('admin.dashboard.new_page') }}</h3>
                <p class="text-xs text-[color:var(--admin-muted)] mt-1 leading-relaxed">{{ __('admin.dashboard.new_page_desc') }}</p>
            </a>
            @endif





            @foreach($moduleDashboard as $contribution)
                @if(isset($contribution['views']['actions']))
                    @include($contribution['views']['actions'], $contribution['data'])
                @endif
            @endforeach

            @if($hasSeo && Route::has('admin.seo.audit'))
            <a href="{{ route('admin.seo.audit') }}" class="admin-card p-5 hover:border-[color:var(--admin-accent)]/50 hover:bg-[color:var(--admin-surface)] transition-all duration-200 group block">
                <div class="flex items-center justify-between mb-3">
                    <span class="w-10 h-10 rounded-lg bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] group-hover:border-[color:var(--admin-accent)]/40 transition-colors flex items-center justify-center text-[color:var(--admin-accent)] text-base"><i class="fa-solid fa-bullseye"></i></span>
                    <span class="text-[color:var(--admin-muted)] group-hover:text-[color:var(--admin-accent)] text-xs font-mono transition-colors">&rarr;</span>
                </div>
                <h3 class="font-bold text-[color:var(--admin-text)] text-sm group-hover:text-[color:var(--admin-accent)] transition-colors">{{ __('admin.dashboard.seo_audit') }}</h3>
                <p class="text-xs text-[color:var(--admin-muted)] mt-1 leading-relaxed">{{ __('admin.dashboard.seo_audit_desc') }}</p>
            </a>
            @endif

            @if($canManageModules && Route::has('admin.modules.index'))
            <a href="{{ route('admin.modules.index') }}" class="admin-card p-5 hover:border-[color:var(--admin-accent)]/50 hover:bg-[color:var(--admin-surface)] transition-all duration-200 group block">
                <div class="flex items-center justify-between mb-3">
                    <span class="w-10 h-10 rounded-lg bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] group-hover:border-[color:var(--admin-accent)]/40 transition-colors flex items-center justify-center text-[color:var(--admin-accent)] text-base"><i class="fa-solid fa-puzzle-piece"></i></span>
                    <span class="text-[color:var(--admin-muted)] group-hover:text-[color:var(--admin-accent)] text-xs font-mono transition-colors">&rarr;</span>
                </div>
                <h3 class="font-bold text-[color:var(--admin-text)] text-sm group-hover:text-[color:var(--admin-accent)] transition-colors">{{ __('admin.dashboard.modules_marketplace') }}</h3>
                <p class="text-xs text-[color:var(--admin-muted)] mt-1 leading-relaxed">{{ __('admin.dashboard.modules_marketplace_desc', ['app' => config('cms.name')]) }}</p>
            </a>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 sm:gap-8">
        @foreach($moduleDashboard as $contribution)
            @if(isset($contribution['views']['details']))
                @include($contribution['views']['details'], $contribution['data'])
            @endif
        @endforeach
    </div>
</div>
@endsection
