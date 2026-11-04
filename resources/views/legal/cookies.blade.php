@extends($layout)
@section('title', __('legal.cookies'))
@section('content')
<main class="max-w-4xl mx-auto px-6 py-12 prose cms-text">
    <h1>{{ __('legal.cookies') }}</h1><p>{{ __('legal.choices') }}</p>
    @foreach($cookieCategories as $category)
        <section><h2>{{ $category['title'] }}</h2><p>{{ $category['description'] }}</p>
            <ul>@foreach($category['cookies'] as $cookie)<li><strong>{{ $cookie['name'] }}</strong> — {{ $cookie['provider'] }}: {{ $cookie['purpose'] }} ({{ $cookie['duration'] }})</li>@endforeach</ul>
        </section>
    @endforeach
    @if(isset($cookieCategories['external']))<p>{{ __('legal.external') }}</p>@endif
    <button type="button" data-dispatch="open-cookie-settings" class="cms-btn cms-btn-primary">{{ __('legal.settings') }}</button>
</main>
@endsection
