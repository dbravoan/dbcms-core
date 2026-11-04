@php
    $brandName = config('cms.name', 'CMS');
    $adminPath = config('cms.admin_path', 'admin');
    $activeMarkets = $activeMarkets ?? [];
    $installedModules = $installedModules ?? [];
@endphp
<!DOCTYPE html>
<html lang="es" class="h-full bg-[#0D0D0D] text-white">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $brandName }} &mdash; Núcleo Base Operativo</title>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #0D0D0D;
            color: #E6E6E6;
        }
        .font-mono {
            font-family: 'Google Sans Code', monospace;
        }
        .cyber-card {
            background-color: #141414;
            border: 1px solid #262626;
            border-radius: 12px;
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .cyber-card:hover {
            border-color: rgba(0, 255, 153, 0.4);
            box-shadow: 0 0 25px rgba(0, 255, 153, 0.1);
            transform: translateY(-2px);
        }
        .btn-neon {
            background-color: #00FF99;
            color: #000000;
            font-family: 'Google Sans Code', monospace;
            font-weight: 700;
            border-radius: 8px;
            transition: all 0.2s ease;
        }
        .btn-neon:hover {
            background-color: #33ffad;
            box-shadow: 0 0 20px rgba(0, 255, 153, 0.4);
        }
        .cursor-blink {
            display: inline-block;
            animation: blink 1s step-end infinite;
        }
        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0; }
        }
    </style>
    @vite(cms_asset_entries())
</head>
<body class="min-h-full flex flex-col justify-between selection:bg-[#00FF99] selection:text-black">

    <!-- Top Navigation -->
    <header class="border-b border-[#222222] bg-[#111111]/80 backdrop-blur sticky top-0 z-50">
        <div class="max-w-6xl mx-auto px-6 py-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <span class="text-xl font-bold font-mono text-[#00FF99]">&gt;_{{ $brandName }}</span>
                <span class="text-[11px] font-mono px-2 py-0.5 rounded bg-[#1C1C1C] text-gray-400 border border-gray-800">CORE v{{ config('cms.version') }}</span>
            </div>
            <div class="flex items-center gap-4">
                <span class="hidden sm:inline-flex items-center gap-1.5 text-xs font-mono text-emerald-400 bg-emerald-950/40 border border-emerald-800/40 px-3 py-1 rounded-full">
                    <span class="w-2 h-2 rounded-full bg-[#00FF99] animate-pulse"></span>
                    Sistema en línea
                </span>
                <a href="{{ url('/' . $adminPath) }}" class="btn-neon text-xs px-4 py-2 flex items-center gap-2">
                    <i class="fa-solid fa-lock text-xs"></i>
                    <span>Acceso Backoffice</span>
                </a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-1 max-w-6xl mx-auto px-6 py-12 sm:py-20 flex flex-col justify-center">
        
        <!-- Hero Section -->
        <div class="text-center max-w-3xl mx-auto mb-16">
            <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-[#181818] border border-gray-800 text-xs font-mono text-gray-300 mb-6">
                <span class="text-[#00FF99] font-bold">&bull;</span>
                <span>INSTALACIÓN LIMPIA AUTÓNOMA</span>
                <span class="text-gray-500">|</span>
                <span class="text-gray-400">Microkernel Foundation</span>
            </div>

            <h1 class="text-4xl sm:text-5xl lg:text-6xl font-black text-white tracking-tight mb-6">
                El núcleo base está listo.<span class="text-[#00FF99] cursor-blink">_</span>
            </h1>

            <p class="text-base sm:text-lg text-gray-400 leading-relaxed font-normal mb-8">
                Has iniciado <strong class="text-white">{{ $brandName }}</strong> sin módulos de contenido activos. 
                El motor central de autenticación, infraestructura multi-mercado, biblioteca multimedia, menús y seguridad OAuth2 para agentes IA se encuentra completamente operativo.
            </p>

            <div class="flex flex-wrap items-center justify-center gap-4">
                <a href="{{ url('/' . $adminPath) }}" class="btn-neon px-6 py-3 text-sm flex items-center gap-2.5">
                    <i class="fa-solid fa-gauge-high"></i>
                    <span>Ir al Panel de Administración</span>
                </a>
                <a href="{{ url('/' . $adminPath . '/modules') }}" class="cyber-card px-6 py-3 text-sm font-mono text-gray-300 hover:text-white flex items-center gap-2.5">
                    <i class="fa-solid fa-cube text-[#00FF99]"></i>
                    <span>Activar Módulos & Extensibilidad</span>
                </a>
            </div>
        </div>

        <!-- System Architecture Highlights -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-16">
            
            <div class="cyber-card p-6">
                <div class="w-10 h-10 rounded-lg bg-[#1F1F1F] border border-gray-800 flex items-center justify-center text-[#00FF99] mb-4">
                    <i class="fa-solid fa-shield-halved text-base"></i>
                </div>
                <h3 class="text-base font-bold text-white mb-2">Autenticación & RBAC</h3>
                <p class="text-xs text-gray-400 leading-relaxed">
                    Gestión de usuarios y roles (<code class="text-gray-300 font-mono">admin</code>, <code class="text-gray-300 font-mono">editor</code>, <code class="text-gray-300 font-mono">marketing</code>) con control granular de permisos.
                </p>
            </div>

            <div class="cyber-card p-6">
                <div class="w-10 h-10 rounded-lg bg-[#1F1F1F] border border-gray-800 flex items-center justify-center text-[#00FF99] mb-4">
                    <i class="fa-solid fa-earth-americas text-base"></i>
                </div>
                <h3 class="text-base font-bold text-white mb-2">Multi-Mercado & Idiomas</h3>
                <p class="text-xs text-gray-400 leading-relaxed">
                    Soporte internacional de divisas y mercados con enrutamiento semántico normalizado y cero duplicación en base de datos.
                </p>
            </div>

            <div class="cyber-card p-6">
                <div class="w-10 h-10 rounded-lg bg-[#1F1F1F] border border-gray-800 flex items-center justify-center text-[#00FF99] mb-4">
                    <i class="fa-solid fa-robot text-base"></i>
                </div>
                <h3 class="text-base font-bold text-white mb-2">Agentes IA & Servidor MCP</h3>
                <p class="text-xs text-gray-400 leading-relaxed">
                    Conexión nativa con Model Context Protocol (MCP), tokens de acceso personal OAuth2 y endpoints semánticos <code class="text-gray-300 font-mono">/llms.txt</code>.
                </p>
            </div>

        </div>

        <!-- Instructions Box -->
        <div class="cyber-card p-6 sm:p-8 bg-gradient-to-r from-[#141414] to-[#1A1A1A] border-gray-800">
            <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
                <div>
                    <h4 class="text-sm font-mono font-bold text-white uppercase tracking-wider mb-2 flex items-center gap-2">
                        <span class="text-[#00FF99]">&gt;</span> Para habilitar páginas y portada web
                    </h4>
                    <p class="text-xs text-gray-400 leading-relaxed max-w-2xl">
                        Ingresa al panel de administración en <code class="font-mono text-white bg-black/50 px-2 py-0.5 rounded border border-gray-700">/{{ $adminPath }}/modules</code> 
                        y activa el módulo <strong>Composite Page Builder</strong> (<code class="font-mono text-[#00FF99]">pages</code>) o el motor editorial <strong>Blog Engine</strong> (<code class="font-mono text-[#00FF99]">blog</code>).
                    </p>
                </div>
                <a href="{{ url('/' . $adminPath . '/modules') }}" class="btn-neon px-5 py-2.5 text-xs whitespace-nowrap">
                    <span>Administrar Módulos &rarr;</span>
                </a>
            </div>
        </div>

    </main>

    <!-- Footer -->
    <footer class="border-t border-[#222222] bg-[#0A0A0A] py-6 text-center text-xs font-mono text-gray-500">
        <div class="max-w-6xl mx-auto px-6 flex flex-col sm:flex-row items-center justify-between gap-3">
            <div>
                &gt;_{{ $brandName }} Enterprise &bull; Arquitectura Limpia Modular
            </div>
            <div class="flex items-center gap-4 text-[11px]">
                <a href="{{ url('/llms.txt') }}" class="hover:text-[#00FF99] transition-colors">/llms.txt</a>
                <span>&bull;</span>
                <a href="{{ url('/sitemap.xml') }}" class="hover:text-[#00FF99] transition-colors">sitemap.xml</a>
                <span>&bull;</span>
                <a href="{{ url('/' . $adminPath) }}" class="hover:text-[#00FF99] transition-colors">Acceso Admin</a>
            </div>
        </div>
    </footer>

</body>
</html>
