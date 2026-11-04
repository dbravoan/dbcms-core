@extends('admin.layout')

@section('title', __('admin.page_titles.page_create'))

@section('content')
<div class="admin-page space-y-6 font-mono text-xs">
    <x-admin.form-header
        title="Crear página"
        description="Construye páginas y páginas de aterrizaje con bloques y optimización SEO/GEO."
        :backRoute="route('admin.pages.index')"
        backLabel="Volver al listado"
    />

    @include('admin.pages._form', ['page' => null, 'markets' => $markets])
</div>
@endsection
