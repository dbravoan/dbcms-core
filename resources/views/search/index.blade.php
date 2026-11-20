@extends($layout)

@section('title', $seoMeta->title)
@section('meta_description', $seoMeta->description)

@section('content')
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-12 space-y-10">
        @include('components.breadcrumbs', ['trail' => $breadcrumbs ?? []])

        <header class="space-y-4 text-center">
            <h1 class="text-3xl sm:text-4xl font-bold tracking-tight">{{ $seoMeta->title }}</h1>
            <p class="text-sm text-slate-500 max-w-2xl mx-auto">
                {{ $seoMeta->description !== '' ? $seoMeta->description : __('Busca en todo el contenido del sitio.') }}
            </p>
        </header>

        {{-- Plain GET form: the query is consolidated into the friendly URL with a 301. --}}
        <x-cms-search.box :action="$searchUrl" :value="''" size="lg" />

        @if($popularTerms->isNotEmpty())
            <section class="space-y-3">
                <h2 class="text-xs font-bold uppercase tracking-wider text-slate-400">
                    {{ __('Búsquedas frecuentes') }}
                </h2>
                <div class="flex flex-wrap gap-2">
                    @foreach($popularTerms as $popular)
                        <a href="{{ $searchUrl }}/{{ \Illuminate\Support\Str::slug($popular->term) }}"
                           class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs text-slate-600 hover:border-slate-400">
                            <i class="fa-solid fa-magnifying-glass text-[10px]" aria-hidden="true"></i>
                            {{ $popular->term }}
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        @if($indexable && $coverage !== [])
            <section class="space-y-3">
                <h2 class="text-xs font-bold uppercase tracking-wider text-slate-400">
                    {{ __('Qué puedes encontrar') }}
                </h2>
                <ul class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-sm text-slate-600">
                    @foreach($coverage as $type => $data)
                        <li class="rounded-xl border border-slate-200 bg-white px-4 py-3">
                            <span class="block text-lg font-bold text-slate-900">{{ $data['indexed'] }}</span>
                            {{ $data['label'] }}
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if(! $indexable)
            <p class="text-xs text-slate-400 text-center">
                <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                {{ __('La página de búsqueda aún no está activa para Google. Se activa desde Filtros y búsquedas en el panel.') }}
            </p>
        @endif
    </div>
@endsection
