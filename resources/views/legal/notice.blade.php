@extends($layout)
@section('title', __('legal.notice'))
@section('content')
<main class="max-w-4xl mx-auto px-6 py-12 prose cms-text">
    <h1>{{ __('legal.notice') }}</h1>
    @include('legal._company')
    <p>{{ __('legal.scope') }}</p>
    <p>{{ __('legal.use') }}</p>
</main>
@endsection
