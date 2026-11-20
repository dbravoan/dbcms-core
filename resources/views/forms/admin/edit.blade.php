@extends('admin.layout')

@section('title', __('admin.forms_ui.edit_title_prefix').' '.$form->name)

@section('content')
<div class="admin-page space-y-6 font-mono text-xs">

    <!-- Header -->
    <div class="flex items-center justify-between pb-4 border-b border-[color:var(--admin-border)]">
        <div>
            <h2 class="text-xl font-bold text-[color:var(--admin-text)] flex items-center gap-2">
                <span class="text-[color:var(--admin-accent)]">&gt;</span> {{ __('admin.forms_ui.edit_title_prefix') }} <span class="text-[color:var(--admin-accent)]">{{ $form->name }}</span>
            </h2>
            <p class="text-xs text-[color:var(--admin-muted)]">{{ __('admin.forms_ui.edit_desc') }}</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.forms.submissions', ['form_id' => $form->id]) }}" class="px-3 py-2 bg-[color:var(--admin-surface)] hover:bg-[color:var(--admin-surface-raised)] text-[color:var(--admin-accent)] border border-[color:var(--admin-border)] rounded-lg transition-colors">
                Ver Leads ({{ $form->submissions()->count() }}) &rarr;
            </a>
            <a href="{{ route('admin.forms.index') }}" class="px-3 py-2 bg-[color:var(--admin-surface-raised)] hover:bg-[color:var(--admin-surface-raised)] text-[color:var(--admin-text)] rounded-lg transition-colors">
                &larr; Volver
            </a>
        </div>
    </div>

    @if ($errors->any())
        <div class="p-4 bg-red-950/40 border border-red-800/80 rounded-xl text-red-300">
            <div class="font-bold text-sm mb-2">{{ __('admin.forms_ui.errors') }}</div>
            <ul class="list-disc pl-5 space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (($form->getAttributes()['pending_revision_id'] ?? null) !== null)
        <a href="{{ route('admin.audit.review', ['type' => 'forms', 'id' => $form->id]) }}" class="block p-4 border border-amber-500 rounded-lg text-amber-300">Cambios pendientes: revisar y aprobar antes de publicar</a>
    @endif

    @include('forms::admin._form', [
        'form' => $form,
        'fields' => $fields,
        'translationsMap' => $translationsMap,
        'languages' => $languages,
        'markets' => $markets,
        'assignedMarketIds' => $assignedMarketIds,
    ])

</div>
@endsection
