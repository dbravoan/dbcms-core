@extends($layout ?? app(\DbCms\Core\Services\Cms\FrontViewResolver::class)->layout())

@section('title', $translation->meta_title ?? $translation->title ?? '')
@section('meta_description', $translation->meta_description ?? '')

@section('content')
<x-cms::content-region class="py-12 gap-y-8">
    @if(!empty($renderedSections))
        @foreach($renderedSections as $sectionHtml)
            {!! $sectionHtml !!}
        @endforeach
    @else
        <div class="text-center py-16">
            <h1 class="text-3xl font-bold">{{ $translation->title ?? __('Página') }}</h1>
            <p>{{ __('Content in preparation') }}</p>
        </div>
    @endif
</x-cms::content-region>
@endsection
