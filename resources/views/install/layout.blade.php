@php
    // The installer is brand-agnostic: it uses neutral dark surfaces and paints
    // every accent with the selected brand accent (defaults to the configured
    // CMS_BRAND, changeable from the "brand" field in step 4).
    $brandRegistry = app(\DbCms\Core\Services\Install\BrandRegistry::class);
    $installBrandId = $installBrandId ?? session('install.brand', (string) config('cms.admin_theme', 'dbcms'));
    $installBrand = $brandRegistry->find((string) $installBrandId) ?? $brandRegistry->neutral();

    $accentHex = ltrim((string) $installBrand['accent'], '#');
    if (strlen($accentHex) === 3) {
        $accentHex = $accentHex[0].$accentHex[0].$accentHex[1].$accentHex[1].$accentHex[2].$accentHex[2];
    }
    $accentRgb = implode(', ', [
        (int) hexdec(substr($accentHex, 0, 2)),
        (int) hexdec(substr($accentHex, 2, 2)),
        (int) hexdec(substr($accentHex, 4, 2)),
    ]);
    $cmsName = (string) config('cms.name', 'CMS');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full"
      style="--install-accent: {{ $installBrand['accent'] }}; --install-accent-rgb: {{ $accentRgb }};">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Instalador Web') | {{ $cmsName }}</title>
    <style>
        [x-cloak] { display: none !important; }
        body, html {
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #121212;
            color: #FFFFFF;
        }
        .btn-neon {
            padding: 12px 24px;
            background-color: var(--install-accent);
            border: 2px solid var(--install-accent);
            color: #0B0B0B;
            font-weight: 700;
            font-size: 0.9rem;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.25s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn-neon:hover:not(:disabled) {
            filter: brightness(1.08);
            box-shadow: 0 0 25px rgba(var(--install-accent-rgb), 0.4);
        }
        .btn-neon:disabled {
            background-color: transparent;
            border-color: #374151;
            color: #6B7280;
            cursor: not-allowed;
            box-shadow: none;
        }
        .cyber-card {
            background-color: #181818;
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 12px;
            box-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.7);
        }
        .step-pill-active {
            border-color: var(--install-accent) !important;
            color: var(--install-accent) !important;
            background-color: rgba(var(--install-accent-rgb), 0.08) !important;
        }

        /* Brand-agnostic accent remap: repaint every former neon-green utility
           used by the installer steps with the selected brand accent. */
        .text-\[\#00FF99\] { color: var(--install-accent) !important; }
        .border-\[\#00FF99\] { border-color: var(--install-accent) !important; }
        .ring-\[\#00FF99\] { --tw-ring-color: var(--install-accent) !important; }
        .focus\:border-\[\#00FF99\]:focus { border-color: var(--install-accent) !important; }
        .focus\:ring-\[\#00FF99\]:focus {
            --tw-ring-color: var(--install-accent) !important;
            border-color: var(--install-accent) !important;
        }
        .bg-\[\#00FF99\]\/10 { background-color: rgba(var(--install-accent-rgb), 0.10) !important; }
        .bg-\[\#00FF99\]\/5 { background-color: rgba(var(--install-accent-rgb), 0.05) !important; }
        .shadow-\[0_0_30px_rgba\(0\,255\,153\,0\.3\)\] {
            box-shadow: 0 0 30px rgba(var(--install-accent-rgb), 0.30) !important;
        }
    </style>
    @vite(cms_asset_entries())
</head>
<body class="min-h-full flex flex-col justify-between py-8 px-4 sm:px-6 lg:px-8">
    <div class="max-w-3xl w-full mx-auto">
        <!-- Brand Header -->
        <header class="text-center mb-6">
            <div class="inline-flex items-center justify-center gap-3">
                @if(!empty($installBrand['icon']))
                    <img src="{{ $installBrand['icon'] }}" alt="{{ $installBrand['name'] }}" class="w-9 h-9 rounded-xl object-cover shrink-0">
                @elseif(!empty($installBrand['logo']))
                    <img src="{{ $installBrand['logo'] }}" alt="{{ $installBrand['name'] }}" class="h-7 w-auto object-contain shrink-0">
                @endif
                <span class="text-2xl sm:text-3xl font-bold tracking-tight text-white">{{ $cmsName }}</span>
            </div>
            <p class="text-xs text-gray-400 mt-1.5">
                Asistente de instalación &bull; Gestor de contenidos desacoplado para equipos y agentes de IA
            </p>
        </header>

        <!-- Progress Steps -->
        <nav aria-label="Progreso de la instalación" class="cyber-card p-3 mb-6">
            <div class="grid grid-cols-5 gap-1.5 text-center text-[11px] font-bold">
                <div class="py-2 px-1 rounded border transition-colors {{ request()->routeIs('install.step1') ? 'step-pill-active' : 'border-gray-800 text-gray-500' }}">
                    [01] ENTORNO
                </div>
                <div class="py-2 px-1 rounded border transition-colors {{ request()->routeIs('install.step2') ? 'step-pill-active' : 'border-gray-800 text-gray-500' }}">
                    [02] BASE DATOS
                </div>
                <div class="py-2 px-1 rounded border transition-colors {{ request()->routeIs('install.step3') ? 'step-pill-active' : 'border-gray-800 text-gray-500' }}">
                    [03] TABLAS
                </div>
                <div class="py-2 px-1 rounded border transition-colors {{ request()->routeIs('install.step4') ? 'step-pill-active' : 'border-gray-800 text-gray-500' }}">
                    [04] AJUSTES
                </div>
                <div class="py-2 px-1 rounded border transition-colors {{ request()->routeIs('install.step5') ? 'step-pill-active' : 'border-gray-800 text-gray-500' }}">
                    [05] LISTO
                </div>
            </div>
        </nav>

        <!-- Main Card Content -->
        <main class="cyber-card p-6 sm:p-8">
            @yield('content')
        </main>
    </div>

    <!-- Footer -->
    <footer class="text-center text-xs text-gray-500 mt-8">
        {{ $cmsName }} &bull; Clean Architecture &bull; Protocolo MCP &bull; Laravel LTS
        @if(!empty($installBrand['edition']))
            &bull; {{ $installBrand['edition'] }}
        @endif
    </footer>
</body>
</html>
