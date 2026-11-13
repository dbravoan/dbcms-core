@extends('admin.layout')
@section('title', 'Componentes reutilizables')
@section('content')
<div class="admin-page space-y-6">
    <x-admin.form-header title="Componentes reutilizables" description="Mantén una composición compartida y permite variaciones controladas de contenido en cada instancia." />
    <x-admin.button :href="route('admin.block-components.create')" variant="primary">Crear componente</x-admin.button>
    <x-admin.table :empty="$components->isEmpty()" emptyTitle="No hay componentes" emptyDescription="Crea una definición con el mismo gestor de bloques de las páginas." :emptyActionRoute="route('admin.block-components.create')" emptyActionLabel="Crear componente">
        <x-slot:header><th class="p-3 text-left">Nombre</th><th class="p-3 text-left">Estado</th><th class="p-3 text-left">Propiedades</th><th class="p-3 text-right">Acciones</th></x-slot:header>
        @foreach($components as $blockComponent)
            <tr><td class="p-3">{{ $blockComponent->translations->firstWhere('locale', app()->getLocale())?->title ?? $blockComponent->translations->first()?->title ?? '#'.$blockComponent->id }}</td>
                <td class="p-3">{{ ! $blockComponent->is_active ? 'Desactivado' : ($blockComponent->is_published ? 'Publicado' : 'Borrador') }}</td>
                <td class="p-3">{{ count($blockComponent->exposed_properties ?? []) }}</td>
                <td class="p-3"><div class="flex flex-wrap justify-end gap-3"><a class="underline" href="{{ route('admin.block-components.edit', $blockComponent) }}">Editar</a><a class="underline" href="{{ route('admin.block-components.preview', $blockComponent) }}" target="_blank" rel="noopener">Previsualizar</a><form method="POST" action="{{ route('admin.block-components.destroy', $blockComponent) }}">@csrf @method('DELETE')<button type="submit" class="underline">Eliminar</button></form></div></td>
            </tr>
        @endforeach
    </x-admin.table>
    {{ $components->links() }}
</div>
@endsection
