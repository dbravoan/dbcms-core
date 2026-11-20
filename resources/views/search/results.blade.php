@extends($layout)

@section('title', $seoMeta->title)
@section('meta_description', $seoMeta->description)

@section('content')
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-12 space-y-8">
        @include('components.breadcrumbs', ['trail' => $breadcrumbs ?? []])

        <header class="space-y-4">
            <h1 class="text-2xl sm:text-3xl font-bold tracking-tight">
                {{ $seoMeta->title }}
            </h1>

            <p class="text-sm text-slate-500">
                {{ trans_choice(':count resultado|:count resultados', $results->total, ['count' => $results->total]) }}
                @if($results->engine === 'fallback')
                    <span class="text-slate-400">· {{ __('resultados aproximados') }}</span>
                @endif
            </p>

            <x-cms-search.box :action="$searchUrl" :value="$term" />
        </header>

        {{-- Facets: one canonical link per entity type. --}}
        @if(count($facets) > 1)
            <nav class="flex flex-wrap items-center gap-2" aria-label="{{ __('Tipos de contenido') }}">
                <a href="{{ $searchUrl }}/{{ $term }}"
                   class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs {{ $entityType === null ? 'border-slate-900 bg-slate-900 text-white font-bold' : 'border-slate-200 bg-white text-slate-600 hover:border-slate-400' }}">
                    {{ __('Todo') }}
                </a>
                @foreach($facets as $facet)
                    <a href="{{ $facet['url'] }}"
                       class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs {{ $facet['key'] === $entityType ? 'border-slate-900 bg-slate-900 text-white font-bold' : 'border-slate-200 bg-white text-slate-600 hover:border-slate-400' }}">
                        {{ $facet['label'] }}
                        <span class="opacity-60">{{ $facet['count'] }}</span>
                    </a>
                @endforeach
            </nav>
        @endif

        {{-- Results --}}
        @if($hits === [])
            <div class="rounded-2xl border border-slate-200 bg-white p-8 text-center space-y-4">
                <p class="text-sm text-slate-600">
                    {{ __('No hay resultados para esta búsqueda.') }}
                </p>

                @if($suggestions !== [])
                    <div class="space-y-2">
                        <p class="text-xs font-bold uppercase tracking-wider text-slate-400">
                            {{ __('Quizá buscabas') }}
                        </p>
                        <ul class="flex flex-wrap justify-center gap-2">
                            @foreach($suggestions as $suggestion)
                                <li>
                                    <a href="{{ $searchUrl }}/{{ \Illuminate\Support\Str::slug($suggestion) }}"
                                       class="inline-flex rounded-full border border-slate-200 px-3 py-1.5 text-xs text-slate-600 hover:border-slate-400">
                                        {{ $suggestion }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        @else
            <ol class="space-y-4">
                @foreach($hits as $hit)
                    <li>
                        <article class="rounded-2xl border border-slate-200 bg-white p-5 transition-colors hover:border-slate-400">
                            <a href="{{ $hit->url }}" class="group block space-y-1.5">
                                <span class="inline-flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wider text-slate-400">
                                    <i class="fa-solid fa-tag text-[9px]" aria-hidden="true"></i>
                                    {{ $hit->label }}
                                </span>
                                <h2 class="text-base font-bold text-slate-900 group-hover:underline">
                                    {{ $hit->title }}
                                </h2>
                                @if($hit->excerpt !== '')
                                    <p class="text-sm text-slate-500 line-clamp-2">
                                        {{ \Illuminate\Support\Str::limit($hit->excerpt, 220) }}
                                    </p>
                                @endif
                            </a>
                        </article>
                    </li>
                @endforeach
            </ol>

            @if($paginator->hasPages())
                <nav class="pt-2" aria-label="{{ __('Paginación') }}">
                    {{ $paginator->onEachSide(1)->links() }}
                </nav>
            @endif
        @endif
    </div>
@endsection
