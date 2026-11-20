@extends($layout)
@section('title', __('legal.privacy'))
@section('content')
<main class="max-w-4xl mx-auto px-6 py-12 prose cms-text">
    <h1>{{ __('legal.privacy') }}</h1>
    @include('legal._company')
    <p>{{ __('legal.scope') }}</p>
    <h2>{{ __('legal.hosting') }}</h2><p>{{ $company['hosting_provider'] }} — {{ $company['hosting_country'] }}</p>
    @foreach($company['processing'] as $processing)
        <section><h2>{{ $processing['purpose'] }}</h2><dl>
            @foreach(['data' => 'data', 'legal_basis' => 'basis', 'retention' => 'retention', 'recipients' => 'recipients', 'transfers' => 'transfers'] as $field => $label)
                <dt class="font-semibold">{{ __('legal.'.$label) }}</dt><dd>{{ $processing[$field] }}</dd>
            @endforeach
        </dl></section>
    @endforeach
    <p>{{ __('legal.rights') }}</p><p><a href="mailto:{{ $company['privacy_email'] }}">{{ $company['privacy_email'] }}</a></p>
</main>
@endsection
