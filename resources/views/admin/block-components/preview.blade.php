@extends(app(\DbCms\Core\Services\Cms\FrontViewResolver::class)->layout())
@section('content')
<main class="max-w-7xl mx-auto p-6"><h1 class="text-xl font-bold">{{ $componentName }}</h1><p>{{ __('Previsualización editorial privada') }}</p>{!! $componentHtml !!}</main>
@endsection
