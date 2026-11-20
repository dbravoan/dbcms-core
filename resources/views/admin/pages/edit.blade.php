@extends('admin.layout')

@php
    $mainTrans = $page->translations->where('locale', 'es')->first() ?? $page->translations->first();
    $pageTitle = $mainTrans?->title ?? 'Página #' . $page->id;
@endphp

@section('title', __('admin.page_titles.page_edit'))

@section('content')
    @if($page->getAttributes()['pending_revision_id'] ?? null)
        <p class="mb-4 rounded border border-amber-500/40 p-3 text-sm text-amber-300">Estás editando una revisión pendiente. La versión publicada se conserva hasta aprobar estos cambios.</p>
    @endif
<div class="admin-page space-y-6 font-mono text-xs">
    <x-admin.form-header
        :title="'Editar página: ' . $pageTitle"
        description="Modifica los bloques dinámicos, traducciones multi-mercado y ajustes SEO de la página."
        :backRoute="route('admin.pages.index')"
        backLabel="Volver al listado"
        :liveUrl="$liveUrl"
        liveLabel="Ver página en vivo &nearr;"
    />

    @include('admin.pages._form', ['page' => $page, 'markets' => $markets])
</div>
@endsection
