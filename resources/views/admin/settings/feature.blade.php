@extends('admin.layout')

@section('title', $title)

@section('content')
<div class="admin-page space-y-6">
    <x-admin.form-header :title="$title" />
    @if($feature === 'search')
        <x-admin.button :href="route('admin.settings.privacy')" variant="ghost">Configurar conservación en Privacidad</x-admin.button>
    @endif
    <form method="POST" action="{{ route('admin.settings.features.update', ['feature' => $feature]) }}" class="space-y-6">
        @csrf
        @method('PUT')
        <x-admin.form-card>
            @foreach($schema as $key => $field)
                @include('admin.modules.partials.setting-field', compact('key', 'field', 'settings'))
            @endforeach
        </x-admin.form-card>
        <x-admin.button type="submit">{{ __('admin.actions.save') }}</x-admin.button>
    </form>
    @if($feature === 'mcp')
        @include('mcp::admin.guide')
    @endif
</div>
@endsection
