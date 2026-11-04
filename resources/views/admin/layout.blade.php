@php
    /** @var \DbCms\Core\Models\User|null $user */
    $user = $currentUser ?? auth()->user();
    $activeModules = $activeModules ?? [];
    $cmsBrandName = $cmsBrandName ?? ($cmsBrand->name ?? (string) config('cms.name', 'CMS'));
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full theme-dbcms bg-[#1A1A1A] text-white">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('admin.dashboard.control_panel')) | {{ config('cms.name') }}</title>
    <style>
        [x-cloak] { display: none !important; }
        body, html {
            font-family: 'Google Sans Code', monospace;
            background-color: #1A1A1A;
            color: #FFFFFF;
        }
        .cursor-blink {
            color: #00FF99;
            display: inline-block;
            animation: blink 1s step-end infinite;
        }
        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0; }
        }
        .btn-neon {
            padding: 8px 16px;
            background-color: transparent;
            border: 1px solid #00FF99;
            color: #00FF99;
            font-family: 'Google Sans Code', monospace;
            font-weight: 700;
            font-size: 0.85rem;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.25s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        .btn-neon:hover {
            background-color: #00FF99;
            color: #1A1A1A;
            box-shadow: 0 0 15px rgba(0, 255, 153, 0.4);
        }
        .cyber-card {
            background-color: #141414;
            border: 1px solid rgba(0, 255, 153, 0.15);
            border-radius: 12px;
            box-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.8);
        }
        .nav-link-active {
            color: #00FF99 !important;
            background-color: rgba(0, 255, 153, 0.12) !important;
            border-left: 3px solid #00FF99;
            font-weight: 700;
        }
        .nav-sublink-active {
            color: #00FF99 !important;
            font-weight: 700;
        }
        /* Scrollbars personalizados globales (vertical y horizontal) */
        * {
            scrollbar-width: thin;
            scrollbar-color: #27272a transparent;
        }
        ::-webkit-scrollbar {
            width: 5px;
            height: 5px;
        }
        ::-webkit-scrollbar-track {
            background: transparent;
        }
        ::-webkit-scrollbar-thumb {
            background: #27272a;
            border-radius: 9999px;
            transition: background 0.2s ease, box-shadow 0.2s ease;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #00FF99;
            box-shadow: 0 0 10px rgba(0, 255, 153, 0.6);
        }
    </style>
    @vite(cms_asset_entries())
</head>
<body data-admin-shell class="h-full flex overflow-hidden" x-data="{ mobileNavOpen: false }" @keydown.escape.window="mobileNavOpen = false">

    <!-- Mobile Navigation Drawer Backdrop -->
    <div x-show="mobileNavOpen"
         @click="mobileNavOpen = false"
         x-transition.opacity
         x-cloak
         class="fixed inset-0 bg-black/80 backdrop-blur-sm z-40 md:hidden"
         style="display: none;"></div>

    <!-- Sidebar Navigation -->
    <aside id="admin-navigation" :class="mobileNavOpen ? 'translate-x-0 shadow-[0_0_50px_rgba(0,0,0,0.9)]' : '-translate-x-full md:translate-x-0'"
           class="fixed md:static inset-y-0 left-0 w-64 h-full bg-[#121212] border-r border-gray-800 flex flex-col shrink-0 z-50 md:z-20 overflow-hidden transition-transform duration-300 ease-in-out select-none">
        <!-- Brand Logo -->
        <div class="p-5 border-b border-gray-800 bg-[#121212] flex items-center justify-between shrink-0 z-10">
            <a href="{{ route('admin.dashboard') }}" class="text-2xl font-bold tracking-tight text-white flex items-center">
                <span>&gt;</span><span class="cursor-blink">_</span><span>{{ config('cms.name') }}</span>
            </a>
            <div class="flex items-center gap-2">
                <span class="text-[10px] font-mono text-[#00FF99] border border-[#00FF99]/40 bg-[#00FF99]/10 px-1.5 py-0.5 rounded font-bold">
                    CORE
                </span>
                <button type="button" @click="mobileNavOpen = false" aria-label="{{ __('admin.common.close') }}" class="md:hidden text-gray-400 hover:text-white p-1 text-lg leading-none">&times;</button>
            </div>
        </div>

        <!-- Grouped Navigation Links -->
        <x-admin.sidebar-navigation :sections="$navSections ?? []" />

        <!-- Sidebar Footer -->
        <div class="p-4 border-t border-gray-800 bg-[#121212] shrink-0 font-mono text-xs">
            @unless(app()->isProduction())
            <div class="mb-3">
                <a href="http://localhost:8088" id="nav-adminer" target="_blank" class="w-full flex items-center justify-center gap-2 py-1.5 px-2 bg-[#1A1A1A] hover:bg-gray-800 border border-gray-700 hover:border-[#00FF99] rounded text-[11px] text-gray-400 hover:text-[#00FF99] transition-colors">
                    <i class="fa-solid fa-database text-xs"></i>
                    <span>Adminer DB</span>
                    <span class="text-gray-500">&nearr;</span>
                </a>
            </div>
            @endunless
            <div class="flex items-center justify-between">
                <div class="truncate max-w-[140px]">
                    <div class="text-white font-bold truncate">{{ $user?->name ?? 'Usuario' }}</div>
                    @if($isAdmin)
                        <span class="text-[9px] bg-[#00FF99]/20 text-[#00FF99] px-1.5 py-0.5 rounded border border-[#00FF99]/40 font-bold">ROOT</span>
                    @elseif($isEditor)
                        <span class="text-[9px] bg-blue-500/20 text-blue-400 px-1.5 py-0.5 rounded border border-blue-500/40 font-bold">EDITOR</span>
                    @elseif($isAuthor)
                        <span class="text-[9px] bg-purple-500/20 text-purple-400 px-1.5 py-0.5 rounded border border-purple-500/40 font-bold">AUTHOR</span>
                    @elseif($isMarketing)
                        <span class="text-[9px] bg-amber-500/20 text-amber-400 px-1.5 py-0.5 rounded border border-amber-500/40 font-bold">MARKETING</span>
                    @elseif($isViewer)
                        <span class="text-[9px] bg-gray-500/20 text-gray-400 px-1.5 py-0.5 rounded border border-gray-500/40 font-bold">VIEWER</span>
                    @endif
                </div>
                <form method="POST" action="{{ route('admin.logout') }}">
                    @csrf
                    <button type="submit" class="text-red-400 hover:text-red-300">Salir</button>
                </form>
            </div>
        </div>
    </aside>

    <!-- Main Content Shell -->
    <div class="flex-1 flex flex-col min-w-0 overflow-hidden bg-[#171717]">
        <!-- Top Action Bar -->
        <header class="h-16 bg-[#121212] border-b border-gray-800 font-mono flex items-center justify-between px-4 sm:px-6 shrink-0 text-xs">
            <div class="flex items-center gap-2 sm:gap-4 min-w-0 flex-1">
                <button type="button"
                        @click="mobileNavOpen = !mobileNavOpen" aria-controls="admin-navigation" :aria-expanded="mobileNavOpen.toString()"
                        class="md:hidden p-2 min-h-[2.5rem] min-w-[2.5rem] inline-flex items-center justify-center text-gray-400 hover:text-white bg-[#1A1A1A] border border-gray-800 rounded-lg focus:outline-none shrink-0"
                        aria-label="{{ __('admin.nav.label') }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
                <div class="flex items-center gap-2 min-w-0">
                    <span class="text-gray-400 hidden sm:inline shrink-0">Backoffice:</span>
                    <span class="text-[#00FF99] font-bold truncate max-w-[40vw] sm:max-w-[220px]">{{ $cmsBrandName }}</span>
                    <span class="text-gray-600 hidden sm:inline shrink-0">/</span>
                    <span class="text-gray-300 font-bold truncate min-w-0">@yield('title')</span>
                </div>
            </div>

            <div class="flex items-center gap-1.5 sm:gap-3 shrink-0">
                @if(!empty($isMaintenanceActive))
                    <a href="{{ route('admin.modules.settings', ['identifier' => 'maintenance']) }}" title="Haga clic para gestionar el mantenimiento" class="hidden lg:flex items-center gap-1.5 px-3 py-1 bg-amber-500/10 hover:bg-amber-500/20 border border-amber-500/30 text-amber-600 rounded-full text-[10px] font-bold animate-pulse transition-all">
                        <i class="fa-solid fa-triangle-exclamation text-[10px]"></i>
                        <span>MODO MANTENIMIENTO ACTIVO (503)</span>
                    </a>
                @endif

                <!-- 1-Click Persona Switcher -->
                @if(!app()->isProduction())
                <div id="nav-persona-switcher" class="hidden lg:flex items-center gap-1 bg-[#1A1A1A] border border-gray-800 p-1 rounded-lg">
                    <span class="text-[11px] text-gray-500 px-2">Rol:</span>
                    <a href="{{ route('admin.quick-login', 'admin') }}" title="Probar como Admin" class="px-2 py-1 rounded {{ auth()->user()?->hasRole('admin') ? 'bg-[#00FF99] text-black font-bold' : 'text-gray-400 hover:text-white' }}">Admin</a>
                    <a href="{{ route('admin.quick-login', 'editor') }}" title="Probar como Editor" class="px-2 py-1 rounded {{ auth()->user()?->hasRole('editor') ? 'bg-[#00FF99] text-black font-bold' : 'text-gray-400 hover:text-white' }}">Editor</a>
                    <a href="{{ route('admin.quick-login', 'author') }}" title="Probar como Author" class="px-2 py-1 rounded {{ auth()->user()?->hasRole('author') ? 'bg-[#00FF99] text-black font-bold' : 'text-gray-400 hover:text-white' }}">Author</a>
                    <a href="{{ route('admin.quick-login', 'marketing') }}" title="Probar como Marketing" class="px-2 py-1 rounded {{ auth()->user()?->hasRole('marketing') ? 'bg-[#00FF99] text-black font-bold' : 'text-gray-400 hover:text-white' }}">Mkt</a>
                    <a href="{{ route('admin.quick-login', 'viewer') }}" title="Probar como Viewer" class="px-2 py-1 rounded {{ auth()->user()?->hasRole('viewer') ? 'bg-[#00FF99] text-black font-bold' : 'text-gray-400 hover:text-white' }}">View</a>
                </div>
                @endif

                <!-- Onboarding Tour Re-Launch Button -->
                <button type="button"
                        data-dispatch="start-onboarding-tour"
                        class="px-2.5 sm:px-3 py-1.5 min-h-[2.25rem] bg-[#1A1A1A] hover:bg-[#00FF99]/10 border border-gray-700 hover:border-[#00FF99] text-gray-300 hover:text-[#00FF99] rounded-lg font-bold transition-all flex items-center gap-1.5 text-xs shrink-0"
                        title="Guía de ayuda interactiva">
                    <span>&gt;_help</span>
                </button>

                <!-- Notifications Center Bell -->
                <div x-data="{
                    open: false,
                    unreadCount: 0,
                    notifications: [],
                    emailEnabled: true,
                    emailPreferenceError: null,
                    loading: false,
                    notifTimer: null,

                    init() {
                        this.fetchNotifications();
                        // Live updates: refresh the badge periodically.
                        this.notifTimer = setInterval(() => this.fetchNotifications(), 25000);
                    },

                    destroy() {
                        if (this.notifTimer) {
                            clearInterval(this.notifTimer);
                        }
                    },

                    fetchNotifications() {
                        fetch('{{ route('admin.notifications.index') }}')
                            .then(res => res.json())
                            .then(data => {
                                this.unreadCount = data.unread_count || 0;
                                this.notifications = data.notifications || [];
                                this.emailEnabled = data.email_enabled !== false;
                            })
                            .catch(() => {});
                    },

                    saveEmailPreference() {
                        this.emailPreferenceError = null;
                        fetch('{{ route('admin.notifications.email-preference') }}', {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Content-Type': 'application/json', 'Accept': 'application/json' },
                            body: JSON.stringify({ enabled: this.emailEnabled })
                        }).then(res => {
                            if (!res.ok) throw new Error('preference');
                            return res.json();
                        }).then(data => { this.emailEnabled = data.email_enabled; })
                          .catch(() => {
                              this.emailEnabled = !this.emailEnabled;
                              this.emailPreferenceError = @js(__('mail.module_notification.preference_failed'));
                          });
                    },

                    markRead(id, actionUrl) {
                        fetch(@js(route('admin.notifications.read', ['id' => '__ID__'])).replace('__ID__', encodeURIComponent(id)), {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
                        }).then(() => {
                            this.fetchNotifications();
                            if (actionUrl) {
                                window.location.href = actionUrl;
                            }
                        });
                    },

                    markAllRead() {
                        fetch('{{ route('admin.notifications.mark-all-read') }}', {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
                        }).then(() => {
                            this.unreadCount = 0;
                            this.notifications = [];
                        });
                    }
                }" class="relative">
                    <button type="button"
                            @click="open = !open; if(open) fetchNotifications()"
                            class="relative p-2 min-h-[2.5rem] min-w-[2.5rem] text-gray-300 hover:text-white bg-[#1A1A1A] hover:bg-gray-800 border border-gray-700 hover:border-[#00FF99] rounded-lg transition-all flex items-center justify-center"
                            title="Centro de notificaciones y alertas">
                        <i class="fa-solid fa-bell text-sm"></i>
                        <span x-show="unreadCount > 0"
                              x-cloak
                              x-text="unreadCount"
                              class="absolute -top-1 -right-1 px-1.5 py-0.2 bg-[#00FF99] text-black font-bold text-[9px] rounded-full min-w-[16px] text-center shadow">
                        </span>
                    </button>

                    <!-- Dropdown Panel -->
                    <div x-show="open"
                         x-cloak
                         @click.outside="open = false"
                         x-transition:enter="transition ease-out duration-150"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100"
                         class="absolute right-0 mt-2 w-80 sm:w-96 bg-[#161616] border border-[#00FF99]/30 shadow-2xl font-mono rounded-xl z-50 overflow-hidden text-xs">
                        
                        <div class="p-3 bg-[#1A1A1A] border-b border-gray-800 text-white flex items-center justify-between">
                            <span class="font-bold flex items-center gap-1.5">
                                <span class="text-[#00FF99]">&gt;</span> Notificaciones
                            </span>
                            <button x-show="unreadCount > 0"
                                    @click="markAllRead()"
                                    class="text-[10px] text-gray-400 hover:text-[#00FF99] underline">
                                Marcar todas como leídas
                            </button>
                        </div>

                        @include('admin.partials.notification-email-preference')

                        <div class="max-h-72 overflow-y-auto divide-y divide-gray-800/60">
                            <template x-if="notifications.length === 0">
                                <div class="py-8 text-center text-gray-500 text-xs">
                                    <i class="fa-solid fa-bell-slash mr-1.5"></i> Sin notificaciones pendientes
                                </div>
                            </template>

                            <template x-for="n in notifications" :key="n.id">
                                <div @click="markRead(n.id, n.action_url)"
                                     class="p-3 hover:bg-gray-800/40 cursor-pointer transition-colors flex items-start gap-2.5">
                                    <template x-if="n.type === 'lead'">
                                        <i class="fa-solid fa-inbox text-[#00FF99] text-base shrink-0 mt-0.5"></i>
                                    </template>
                                    <template x-if="n.type === 'order'">
                                        <i class="fa-solid fa-cart-shopping text-[#00FF99] text-base shrink-0 mt-0.5"></i>
                                    </template>
                                    <template x-if="n.type !== 'lead' && n.type !== 'order'">
                                        <i class="fa-solid fa-bell text-[#00FF99] text-base shrink-0 mt-0.5"></i>
                                    </template>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center justify-between gap-1">
                                            <span class="font-bold text-white truncate text-[11px]" x-text="n.title"></span>
                                            <span class="text-[9px] uppercase px-1 rounded bg-[#1A1A1A] text-gray-400 border border-gray-800 shrink-0" x-text="n.module"></span>
                                        </div>
                                        <p class="text-[10px] text-gray-400 mt-0.5 line-clamp-2" x-text="n.message"></p>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- Direct Frontoffice Link -->
                <a href="{{ url('/') }}"
                   target="_blank"
                   class="btn-neon text-xs py-1.5 px-2.5 sm:px-3 min-h-[2.25rem] shrink-0">
                    <span class="hidden sm:inline">Web &nearr;</span>
                    <span class="sm:hidden"><i class="fa-solid fa-arrow-up-right-from-square"></i></span>
                </a>
            </div>
        </header>

        <!-- Main Body Area -->
        <main class="flex-1 overflow-y-auto p-6 md:p-8">
            <!-- Flash Message Alerts -->
            @if(session('success'))
                <div data-dismissable class="mb-6 p-4 rounded-xl bg-[#00FF99]/10 border border-[#00FF99]/30 text-[#00FF99] font-mono text-xs flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <i class="fa-solid fa-circle-check text-base"></i>
                        <span>{{ session('success') }}</span>
                    </div>
                    <button type="button" data-dismiss class="text-gray-400 hover:text-white">&times;</button>
                </div>
            @endif

            @if(session('error'))
                <div data-dismissable class="mb-6 p-4 rounded-xl bg-red-500/10 border border-red-500/30 text-red-400 font-mono text-xs flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <i class="fa-solid fa-triangle-exclamation text-base"></i>
                        <span>{{ session('error') }}</span>
                    </div>
                    <button type="button" data-dismiss class="text-gray-400 hover:text-white">&times;</button>
                </div>
            @endif

            @if(isset($errors) && $errors->any())
                <div class="mb-6 p-4 rounded-xl bg-red-500/10 border border-red-500/30 text-red-400 font-mono text-xs">
                    <ul class="list-disc pl-4 space-y-1">
                        @foreach($errors->all() as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </main>
    </div>

    <!-- Unified Canonical Components -->
    <x-admin.filemanager-modal />
    <x-admin.delete-modal />
    <x-admin.confirm-modal />
    <x-admin.onboarding-tutorial />

    <!-- Autonomous Module UI Stacks & Injections -->
    @stack('admin_modals')
    @stack('admin_scripts')

</body>
</html>
