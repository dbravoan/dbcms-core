@extends('install.layout')

@section('title', 'Paso 3: Aprovisionamiento de Tablas, Módulos y Datos')

@section('content')
<div x-data="{
    running: false,
    migrated: false,
    withDemo: false,
    errorMessage: '',
    logs: [
        'Preparado para iniciar el aprovisionamiento en el servidor MySQL.',
        'Se crearán las tablas del núcleo, módulos desacoplados, llaves Passport OAuth2 y storage:link.'
    ],
    startProvisioning() {
        this.running = true;
        this.errorMessage = '';
        this.logs.push('Iniciando aprovisionamiento en el servidor...');

        fetch('{{ route('install.run-migrations') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                with_demo: this.withDemo
            })
        })
        .then(res => res.json())
        .then(data => {
            this.running = false;
            if (data.success) {
                this.migrated = true;
                if (data.logs && Array.isArray(data.logs)) {
                    data.logs.forEach(l => this.logs.push('[OK] ' + l));
                }
                this.logs.push('[OK] ¡Aprovisionamiento finalizado con éxito!');
            } else {
                this.errorMessage = data.message;
                this.logs.push('[ERROR] ' + data.message);
            }
        })
        .catch(err => {
            this.running = false;
            this.errorMessage = 'Fallo de red o tiempo de espera agotado.';
            this.logs.push('[ERROR] Fallo en la comunicación con el servidor.');
        });
    }
}" class="space-y-6">
    <!-- Header -->
    <div>
        <h2 class="text-xl font-bold text-white flex items-center gap-2">
            <span class="text-[#00FF99]">&gt;</span> Aprovisionamiento de Tablas, Módulos y Datos
        </h2>
        <p class="text-xs text-gray-400 mt-1 font-mono">
            El sistema construirá el esquema relacional, registrará las extensiones activas y configurará la seguridad OAuth2 para los agentes de IA.
        </p>
    </div>

    <!-- Explanatory Tasks Card -->
    <div class="bg-[#121212] border border-gray-800 rounded-xl p-4 space-y-2 text-xs font-mono">
        <span class="text-gray-400 font-bold uppercase text-[11px] block">Tareas que se ejecutarán automáticamente:</span>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-gray-300">
            <div class="flex items-center gap-2">
                <span class="text-[#00FF99]">1.</span>
                <span>Tablas del núcleo (mercados, idiomas, usuarios, menús)</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-[#00FF99]">2.</span>
                <span>Migraciones de módulos en <code>modules/</code></span>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-[#00FF99]">3.</span>
                <span>Claves de cifrado y cliente personal Laravel Passport</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-[#00FF99]">4.</span>
                <span>Enlace simbólico público <code>storage:link</code></span>
            </div>
        </div>
    </div>

    <!-- Data Seeding Selector -->
    <div class="space-y-3" x-show="!migrated">
        <label class="block text-xs font-mono font-bold text-gray-400 uppercase">Selecciona el tipo de instalación inicial:</label>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <!-- Option 1: Full Demo -->
            <div @click="withDemo = true"
                 :class="withDemo ? 'border-[#00FF99] bg-[#00FF99]/5' : 'border-gray-800 bg-[#121212]'"
                 class="p-4 rounded-xl border cursor-pointer transition-all space-y-2">
                <div class="flex items-center justify-between">
                    <span class="font-mono text-xs font-bold text-white flex items-center gap-2">
                        <span :class="withDemo ? 'text-[#00FF99]' : 'text-gray-500'">●</span>
                        <span>Instalación Completa con Datos Demo</span>
                    </span>
                    <span class="text-[10px] font-mono px-2 py-0.5 rounded bg-[#00FF99]/10 text-[#00FF99] font-bold">Recomendado</span>
                </div>
                <p class="text-[11px] text-gray-400 leading-relaxed font-mono">
                    Puebla el sitio con 3 mercados (España, EE. UU., México), 3 idiomas, artículos técnicos redactados con estilo periodístico natural, catálogo de productos multidivisa, galerías y tema visual activo.
                </p>
            </div>

            <!-- Option 2: Clean Base -->
            <div @click="withDemo = false"
                 :class="!withDemo ? 'border-[#00FF99] bg-[#00FF99]/5' : 'border-gray-800 bg-[#121212]'"
                 class="p-4 rounded-xl border cursor-pointer transition-all space-y-2">
                <div class="flex items-center justify-between">
                    <span class="font-mono text-xs font-bold text-white flex items-center gap-2">
                        <span :class="!withDemo ? 'text-[#00FF99]' : 'text-gray-500'">●</span>
                        <span>Instalación Limpia (Solo Estructura)</span>
                    </span>
                    <span class="text-[10px] font-mono px-2 py-0.5 rounded bg-gray-800 text-gray-400">Minimalista</span>
                </div>
                <p class="text-[11px] text-gray-400 leading-relaxed font-mono">
                    Genera únicamente las tablas vacías y los roles esenciales de administración. Ideal para proyectos en los que empezarás a crear todo el contenido desde cero.
                </p>
            </div>
        </div>
    </div>

    <!-- Terminal Output Log Window -->
    <div class="bg-black/90 border border-gray-800 rounded-xl p-4 font-mono text-xs text-gray-300 space-y-1.5 h-44 overflow-y-auto shadow-inner">
        <template x-for="(log, idx) in logs" :key="idx">
            <div class="flex items-start gap-2">
                <span class="text-[#00FF99] font-bold">&gt;</span>
                <span x-text="log"></span>
            </div>
        </template>
    </div>

    <div class="flex items-center justify-between pt-4 border-t border-gray-800">
        <a href="{{ route('install.step2') }}" class="text-xs font-mono text-gray-400 hover:text-white transition-colors">
            &larr; Volver
        </a>

        <div class="flex items-center gap-3">
            <button type="button"
                    x-show="!migrated"
                    @click="startProvisioning()"
                    :disabled="running"
                    class="btn-neon">
                <span x-show="running" class="text-white"><i class="fa-solid fa-spinner fa-spin mr-1"></i></span>
                <span x-text="running ? 'Aprovisionando...' : 'Iniciar Aprovisionamiento'"></span>
            </button>

            <a href="{{ route('install.step4') }}"
               x-show="migrated"
               x-transition
               class="btn-neon">
                Siguiente: Administrador &rarr;
            </a>
        </div>
    </div>
</div>
@endsection
