@extends('install.layout')

@section('title', 'Paso 2: Conexión y Base de Datos MySQL')

@section('content')
<div x-data="{
    host: '{{ $config['host'] }}',
    port: '{{ $config['port'] }}',
    database: '{{ $config['database'] }}',
    username: '{{ $config['username'] }}',
    password: '{{ $config['password'] }}',
    autoCreate: true,
    testing: false,
    testSuccess: null,
    testMessage: '',
    testConnection() {
        this.testing = true;
        this.testSuccess = null;
        this.testMessage = '';

        fetch('{{ route('install.test-db') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                host: this.host,
                port: this.port,
                database: this.database,
                username: this.username,
                password: this.password,
                auto_create: this.autoCreate
            })
        })
        .then(res => res.json())
        .then(data => {
            this.testing = false;
            this.testSuccess = data.success;
            this.testMessage = data.message;
        })
        .catch(err => {
            this.testing = false;
            this.testSuccess = false;
            this.testMessage = 'Error de red al comprobar la conexión.';
        });
    }
}" class="space-y-6">
    <!-- Header -->
    <div>
        <h2 class="text-xl font-bold text-white flex items-center gap-2">
            <span class="text-[#00FF99]">&gt;</span> Configuración de la Base de Datos MySQL
        </h2>
        <p class="text-xs text-gray-400 mt-1 font-mono">
            {{ config('cms.name') }} almacena los contenidos, esquemas de mercados y tokens OAuth2 en tablas relacionales optimizadas con juego de caracteres UTF-8 extendido (utf8mb4).
        </p>
    </div>

    <!-- Live Test Feedback Banner -->
    <div x-show="testMessage"
         x-transition
         :class="testSuccess ? 'bg-[#00FF99]/10 border-[#00FF99] text-[#00FF99]' : 'bg-red-500/10 border-red-500 text-red-400'"
         class="p-4 rounded-lg border font-mono text-xs flex items-center gap-2"
         style="display: none;">
        <i :class="testSuccess ? 'fa-solid fa-check text-[#00FF99]' : 'fa-solid fa-xmark text-red-400'"></i>
        <span x-text="testMessage"></span>
    </div>

    <form method="POST" action="{{ route('install.save-db') }}" class="space-y-4">
        @csrf

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="sm:col-span-2 space-y-1">
                <label class="block text-xs font-mono font-bold text-gray-400 uppercase">Servidor MySQL (Host)</label>
                <input type="text" name="host" x-model="host" required
                       placeholder="127.0.0.1 o localhost"
                       class="w-full bg-[#121212] border border-gray-700 focus:border-[#00FF99] focus:ring-1 focus:ring-[#00FF99] rounded-lg px-4 py-2.5 text-white font-mono text-sm">
            </div>

            <div class="space-y-1">
                <label class="block text-xs font-mono font-bold text-gray-400 uppercase">Puerto</label>
                <input type="text" name="port" x-model="port" required
                       class="w-full bg-[#121212] border border-gray-700 focus:border-[#00FF99] focus:ring-1 focus:ring-[#00FF99] rounded-lg px-4 py-2.5 text-white font-mono text-sm">
            </div>
        </div>

        <div class="space-y-1">
            <label class="block text-xs font-mono font-bold text-gray-400 uppercase">Nombre de la Base de Datos</label>
            <input type="text" name="database" x-model="database" required
                   placeholder="dbcms"
                   class="w-full bg-[#121212] border border-gray-700 focus:border-[#00FF99] focus:ring-1 focus:ring-[#00FF99] rounded-lg px-4 py-2.5 text-white font-mono text-sm">
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="space-y-1">
                <label class="block text-xs font-mono font-bold text-gray-400 uppercase">Usuario MySQL</label>
                <input type="text" name="username" x-model="username" required
                       placeholder="root o usuario de base de datos"
                       class="w-full bg-[#121212] border border-gray-700 focus:border-[#00FF99] focus:ring-1 focus:ring-[#00FF99] rounded-lg px-4 py-2.5 text-white font-mono text-sm">
            </div>

            <div class="space-y-1">
                <label class="block text-xs font-mono font-bold text-gray-400 uppercase">Contraseña</label>
                <input type="password" name="password" x-model="password"
                       class="w-full bg-[#121212] border border-gray-700 focus:border-[#00FF99] focus:ring-1 focus:ring-[#00FF99] rounded-lg px-4 py-2.5 text-white font-mono text-sm"
                       placeholder="Contraseña de MySQL">
            </div>
        </div>

        <!-- Auto-Create Checkbox -->
        <div class="bg-[#121212] p-3 rounded-lg border border-gray-800 flex items-center justify-between text-xs font-mono">
            <label for="autoCreateCheck" class="text-gray-300 flex items-center gap-2 cursor-pointer">
                <input type="checkbox" id="autoCreateCheck" name="auto_create" value="1" x-model="autoCreate" class="rounded border-gray-700 bg-gray-900 text-[#00FF99] focus:ring-0">
                <span>Crear la base de datos automáticamente si aún no existe en el servidor</span>
            </label>
            <span class="text-gray-500 text-[10px] hidden sm:inline">utf8mb4_unicode_ci</span>
        </div>

        <div class="flex items-center justify-between pt-4 border-t border-gray-800">
            <a href="{{ route('install.step1') }}" class="text-xs font-mono text-gray-400 hover:text-white transition-colors">
                &larr; Volver
            </a>

            <div class="flex items-center gap-3">
                <button type="button" @click="testConnection()" :disabled="testing"
                        class="px-4 py-2.5 bg-[#121212] hover:bg-gray-800 border border-gray-700 text-gray-200 text-xs font-mono font-bold rounded-lg transition-colors flex items-center gap-1.5">
                    <span x-show="testing" class="text-[#00FF99]"><i class="fa-solid fa-spinner fa-spin"></i></span>
                    <span x-text="testing ? 'Probando...' : 'Probar Conexión'"></span>
                </button>

                <button type="submit" class="btn-neon">
                    Guardar y Continuar &rarr;
                </button>
            </div>
        </div>
    </form>
</div>
@endsection
