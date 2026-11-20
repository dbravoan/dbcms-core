@extends('install.layout')

@section('title', 'Acceso al instalador')

@section('content')
    @if($configured)
    <form method="post" action="{{ route('install.access.store') }}" class="cyber-card p-5 space-y-4">
        @csrf
        <label for="install_token" class="block font-semibold">Código de instalación</label>
        <p id="install-token-help" class="text-sm text-gray-300">Encontrarás el código de un solo uso en <code>CMS_INSTALL_TOKEN</code> en el archivo <code>.env</code> del servidor.</p>
        <input id="install_token" name="install_token" type="password" required minlength="64" maxlength="64"
               autocomplete="off" aria-describedby="install-token-help" class="w-full rounded border border-gray-700 bg-[#121212] px-3 py-2">
        @error('install_token')
            <p role="alert" class="text-sm text-red-300">{{ $message }}</p>
        @enderror
        <button type="submit" class="btn-neon">Abrir el asistente</button>
    </form>
    @else
        <p>Configura <code>CMS_INSTALL_TOKEN</code> en el servidor para habilitar el asistente web o ejecuta <code>php artisan cms:install</code>.</p>
    @endif
@endsection
