@extends('admin.layout')
@section('title', 'Paquetes de bloques')
@section('content')
<div class="admin-page space-y-6">
    <x-admin.table :empty="count($packages) === 0" empty-title="No hay paquetes instalados">
        <x-slot:header><th class="p-4">Paquete</th><th class="p-4">Estado</th><th class="p-4">Acciones</th></x-slot:header>
        @foreach($packages as $identifier => $package)
            @php($enabled = $package['enabled'])
            <tr>
                <td class="p-4"><strong>{{ $package['name'] }}</strong><p class="text-xs opacity-70">{{ $package['version'] }} · {{ $package['package'] }}</p></td>
                <td class="p-4">{{ $enabled ? 'Activo' : 'Oculto' }}</td>
                <td class="p-4 flex flex-wrap gap-3">
                    <form action="{{ route('admin.block-packages.toggle', $identifier) }}" method="POST">@csrf<input type="hidden" name="enabled" value="{{ $enabled ? 0 : 1 }}"><x-admin.button type="submit" variant="secondary">{{ $enabled ? 'Ocultar' : 'Activar' }}</x-admin.button></form>
                </td>
            </tr>
        @endforeach
    </x-admin.table>
    <p class="text-sm opacity-70">Ocultar un paquete mantiene sus bloques guardados y deja de mostrarlos en la web. Al reactivarlo vuelven a estar disponibles.</p>
</div>
@endsection
