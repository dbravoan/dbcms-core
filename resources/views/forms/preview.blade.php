@php($layout = app(\DbCms\Core\Services\Cms\FrontViewResolver::class)->layout())
@extends($layout)

@section('title', $formTitle)
@section('content')
<div class="max-w-3xl mx-auto px-4 py-12">
    <div class="{{ $wrapperClass }}">
        <h1 class="text-lg font-bold cms-text">{{ $formTitle }}</h1>
        @if($formDescription)<p class="cms-text-muted mt-2">{{ $formDescription }}</p>@endif
        <fieldset disabled class="space-y-4 mt-6" aria-label="{{ __('Vista previa del formulario') }}">
            <x-cms-form.fields :schema="$schema" variant="cyber" layout="grid" honeypot="_hp_check" />
            <button type="button" class="cms-btn cms-btn-primary w-full py-3">{{ $btnLabel }}</button>
        </fieldset>
    </div>
</div>
@endsection
