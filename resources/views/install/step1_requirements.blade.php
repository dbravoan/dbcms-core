@extends('install.layout')

@section('title', 'Paso 1: ¿Qué es '.config('cms.name').'? & Comprobación del servidor')

@section('content')
<div class="space-y-6">
    <!-- Introduction / Onboarding Box -->
    <div class="bg-[#121212] border border-gray-800 rounded-xl p-5 space-y-3">
        <div class="flex items-center gap-2 text-[#00FF99] font-bold text-sm">
            <span>&gt;</span>
            <span>¿Qué es {{ config('cms.name') }} y en qué se diferencia de otros gestores?</span>
        </div>
        <p class="text-xs text-gray-300 leading-relaxed">
            La mayoría de plataformas web tradicionales nacieron hace dos décadas. Para añadir idiomas o países, clonan bases de datos completas; para conectar inteligencias artificiales, dependen de parches que comprometen claves maestras.
        </p>
        <p class="text-xs text-gray-300 leading-relaxed">
            <strong>{{ config('cms.name') }}</strong> está diseñado desde la raíz para resolver estos dolores mediante Clean Architecture:
        </p>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-1 text-[11px] font-mono">
            <div class="bg-[#181818] p-3 rounded-lg border border-gray-800">
                <span class="text-[#00FF99] font-bold block mb-1"><i class="fa-solid fa-globe mr-1"></i> Multi-mercado real</span>
                <span class="text-gray-400">Un solo contenido lógico con traducciones y tarifas aisladas para España, EE. UU. o México. Sin duplicar tablas.</span>
            </div>
            <div class="bg-[#181818] p-3 rounded-lg border border-gray-800">
                <span class="text-[#00FF99] font-bold block mb-1"><i class="fa-solid fa-robot mr-1"></i> Conexión MCP opcional</span>
                <span class="text-gray-400">El módulo MCP añade <code>/mcp/cms</code>, protegido con OAuth2 y permisos, para conectar asistentes.</span>
            </div>
            <div class="bg-[#181818] p-3 rounded-lg border border-gray-800">
                <span class="text-[#00FF99] font-bold block mb-1"><i class="fa-solid fa-puzzle-piece mr-1"></i> Módulos desacoplados</span>
                <span class="text-gray-400">El núcleo incluye páginas y menús. Blog, tienda y formularios se añaden mediante módulos opcionales.</span>
            </div>
        </div>
    </div>

    <!-- Configuration File Status -->
    <div class="bg-[#121212] border border-gray-800 rounded-lg p-4 font-mono text-xs flex items-center justify-between">
        <div class="flex items-center gap-2">
            <span class="text-[#00FF99]">●</span>
            <span class="text-gray-300">Archivo de configuración <code>.env</code>:</span>
        </div>
        <span class="text-[#00FF99] font-bold flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
            Inicializado con clave segura
        </span>
    </div>

    <!-- Requirements List -->
    <div class="space-y-3 bg-[#121212] p-4 rounded-lg border border-gray-800 font-mono text-xs">
        <div class="text-[11px] text-gray-400 font-bold uppercase tracking-wider pb-2 border-b border-gray-800">
            Módulos PHP del Servidor
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2">
            @foreach($requirements as $name => $passed)
                <div class="flex items-center justify-between py-1 border-b border-gray-900">
                    <span class="text-gray-300">{{ $name }}</span>
                    @if($passed)
                        <span class="text-[#00FF99] font-bold flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            OK
                        </span>
                    @else
                        <span class="text-red-400 font-bold flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                            FALTA
                        </span>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    <!-- Permissions List -->
    <div class="space-y-3 bg-[#121212] p-4 rounded-lg border border-gray-800 font-mono text-xs" x-data="{ copiedCmd: false }">
        <div class="flex items-center justify-between pb-2 border-b border-gray-800">
            <span class="text-[11px] text-gray-400 font-bold uppercase tracking-wider">Permisos de Escritura en Disco</span>
            <span class="text-gray-500 text-[10px]">Caché, plantillas y archivos subidos</span>
        </div>
        <div class="space-y-1.5">
            @foreach($permissions as $name => $passed)
                <div class="flex items-center justify-between py-1 border-b border-gray-900">
                    <span class="text-gray-300">{{ $name }}</span>
                    @if($passed)
                        <span class="text-[#00FF99] font-bold flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            ESCRITURA OK
                        </span>
                    @else
                        <span class="text-red-400 font-bold flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                            SIN PERMISO
                        </span>
                    @endif
                </div>
            @endforeach
        </div>

        @if(! $canProceed)
            <div class="bg-red-900/20 border border-red-500/30 rounded-lg p-3 text-[11px] text-red-300 space-y-2 mt-3">
                <p>Algunos directorios no tienen permisos de escritura. Ejecuta este comando en la terminal de tu servidor:</p>
                <div class="flex items-center justify-between bg-black/60 p-2 rounded border border-red-500/40 text-gray-200">
                    <code>sudo chown -R www-data:www-data storage bootstrap/cache && sudo chmod -R 775 storage bootstrap/cache</code>
                    <button type="button" @click="navigator.clipboard.writeText('sudo chown -R www-data:www-data storage bootstrap/cache && sudo chmod -R 775 storage bootstrap/cache'); copiedCmd = true; setTimeout(() => copiedCmd = false, 2500)" class="px-2 py-1 bg-red-950 hover:bg-red-900 text-red-200 rounded font-bold transition-colors ml-2">
                        <span x-text="copiedCmd ? '¡Copiado!' : 'Copiar'"></span>
                    </button>
                </div>
            </div>
        @endif
    </div>

    <div class="flex items-center justify-between pt-4 border-t border-gray-800">
        <span class="text-xs text-gray-500 font-mono">Paso 1 de 5</span>
        <a href="{{ route('install.step2') }}"
           class="btn-neon {{ ! $canProceed ? 'pointer-events-none opacity-40' : '' }}">
            Continuar a la Base de Datos &rarr;
        </a>
    </div>
</div>
@endsection
