@extends('install.layout')

@section('title', 'Paso 5: ¡Instalación Completada!')

@section('content')
<div class="space-y-6 text-center py-2">
    <!-- Success Badge -->
    <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-[#00FF99]/10 border-2 border-[#00FF99] text-[#00FF99] mb-1 shadow-[0_0_30px_rgba(0,255,153,0.3)]">
        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
    </div>

    <div>
        <h2 class="text-2xl font-bold text-white tracking-tight">
            <span>&gt;</span> ¡{{ config('cms.name') }} ha quedado instalado con éxito!
        </h2>
        <p class="text-xs font-mono text-gray-400 mt-1 max-w-lg mx-auto leading-relaxed">
            Hemos generado el archivo de bloqueo <code class="text-[#00FF99] bg-[#121212] px-2 py-0.5 rounded">storage/installed</code>.
            Por seguridad, las rutas del instalador han quedado deshabilitadas de forma permanente.
        </p>
    </div>

    <!-- Quick Links Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-left font-mono text-xs">
        <div class="bg-[#121212] border border-gray-800 rounded-xl p-4 space-y-2">
            <span class="text-[#00FF99] font-bold block text-xs"><i class="fa-solid fa-desktop mr-1"></i> Panel de Administración</span>
            <div class="text-gray-400">Ruta personalizada: <span class="text-white font-bold">/{{ $adminPath }}</span></div>
            <div class="text-gray-400">URL directa: <a href="{{ $loginUrl }}" class="text-[#00FF99] underline hover:text-white block truncate">{{ $loginUrl }}</a></div>
        </div>

        <div class="bg-[#121212] border border-gray-800 rounded-xl p-4 space-y-2">
            <span class="text-[#00FF99] font-bold block text-xs"><i class="fa-solid fa-globe mr-1"></i> Portada del Sitio</span>
            <div class="text-gray-400">Gestiona los idiomas y mercados del sitio desde el panel de administración.</div>
            <div class="text-gray-400">URL pública: <a href="{{ url('/') }}" class="text-[#00FF99] underline hover:text-white block truncate">{{ url('/') }}</a></div>
        </div>

        <div class="sm:col-span-2 bg-[#121212] border border-gray-800 rounded-xl p-4 space-y-2">
            <span class="text-purple-400 font-bold block text-xs flex items-center gap-1.5">
                <span><i class="fa-solid fa-robot"></i></span>
                <span>Conexión con Agentes de Inteligencia Artificial (Protocolo MCP)</span>
            </span>
            <p class="text-gray-400 text-[11px] leading-relaxed">
                Para conectar asistentes, instala y activa el módulo MCP. Su endpoint es <code class="text-purple-300">/mcp/cms</code>. Después configura un cliente OAuth2 con los permisos necesarios desde el panel de administración.
            </p>
        </div>
    </div>

    <!-- Actions -->
    <div class="pt-3 flex flex-col sm:flex-row items-center justify-center gap-3">
        <a href="{{ $loginUrl }}" class="btn-neon w-full sm:w-auto">
            Acceder al Panel de Control &rarr;
        </a>
        <a href="{{ url('/') }}" class="py-3 px-6 bg-[#181818] hover:bg-gray-800 text-gray-300 border border-gray-700 hover:border-gray-500 rounded-lg text-xs font-mono font-bold transition-all w-full sm:w-auto">
            Ver Portada Pública
        </a>
    </div>
</div>
@endsection
