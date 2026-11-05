@extends('admin.layout')

@section('title', __('admin.page_titles.seo_audit'))

@section('content')
<div class="admin-page space-y-6 font-mono text-xs">

    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="admin-page-title">
                <span>&gt;</span> {{ __('admin.seo_audit.title') }}
            </h1>
            <p class="text-[color:var(--admin-muted)] mt-1">
                {{ __('admin.seo.editorial_heuristic') }}
            </p>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ url('/llms.txt') }}" target="_blank" class="px-3 py-1.5 bg-[color:var(--admin-surface)] hover:bg-[color:var(--admin-surface-raised)] border border-[color:var(--admin-border)] text-[color:var(--admin-accent)] rounded-lg">
                Ver /llms.txt &nearr;
            </a>
            <a href="{{ url('/sitemap.xml') }}" target="_blank" class="px-3 py-1.5 bg-[color:var(--admin-surface)] hover:bg-[color:var(--admin-surface-raised)] border border-[color:var(--admin-border)] text-[color:var(--admin-text)] rounded-lg">
                Ver sitemap.xml &nearr;
            </a>
        </div>
    </div>

    <!-- KPI Metric Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        @foreach($counts as $count)
        <x-admin.form-card class="space-y-1">
            <div class="text-[10px] text-[color:var(--admin-muted)] uppercase font-bold">{{ $count['label'] }}</div>
            <div class="text-2xl font-bold text-[color:var(--admin-text)]">{{ $count['count'] }}</div>
        </x-admin.form-card>
        @endforeach

        <x-admin.form-card class="space-y-1">
            <div class="text-[10px] text-[color:var(--admin-muted)] uppercase font-bold">{{ __('admin.seo_audit.active_markets') }}</div>
            <div class="text-2xl font-bold text-[color:var(--admin-accent)]">{{ $markets->count() }}</div>
            <div class="text-[10px] text-[color:var(--admin-muted)]">{{ __('admin.seo_audit.hreflang') }}</div>
        </x-admin.form-card>

        <x-admin.form-card class="space-y-1">
            <div class="text-[10px] text-[color:var(--admin-muted)] uppercase font-bold">{{ __('admin.seo_audit.ai_geo') }}</div>
            <div class="text-base font-bold text-[color:var(--admin-accent)]">{{ $geoRoutesAvailable ? __('admin.seo_audit.routes_available') : __('admin.seo_audit.routes_unavailable') }}</div>
            <div class="text-[10px] text-[color:var(--admin-muted)]">{{ __('admin.seo_audit.not_measured') }}</div>
        </x-admin.form-card>
    </div>

    <!-- Audited Content Datatable -->
    <x-admin.form-card class="overflow-hidden">
        <div class="p-4 border-b border-[color:var(--admin-border)] bg-[color:var(--admin-surface)] flex items-center justify-between">
            <h3 class="font-bold text-[color:var(--admin-text)] uppercase text-xs">&gt; {{ __('admin.seo_audit.citability_title') }}</h3>
            <span class="text-[10px] text-[color:var(--admin-muted)]">{{ __('admin.seo_audit.last_posts') }}</span>
        </div>

        <x-admin.table :empty="empty($auditedEntities)" emptyTitle="No hay publicaciones para auditar" emptyDescription="Publica contenido para ver su evaluación GEO en tiempo real.">
            <x-slot:header>
                <th class="py-3 px-4 font-bold uppercase tracking-wider text-[color:var(--admin-muted)]">{{ __('admin.seo_audit.col_content') }}</th>
                <th class="py-3 px-4 font-bold uppercase tracking-wider text-[color:var(--admin-muted)]">{{ __('admin.seo_audit.col_type') }}</th>
                <th class="py-3 px-4 font-bold uppercase tracking-wider text-[color:var(--admin-muted)]">{{ __('admin.seo_audit.col_words') }}</th>
                <th class="py-3 px-4 font-bold uppercase tracking-wider text-[color:var(--admin-muted)]">{{ __('admin.seo_audit.col_score') }}</th>
                <th class="py-3 px-4 font-bold uppercase tracking-wider text-[color:var(--admin-muted)]">{{ __('admin.seo_audit.col_recommendations') }}</th>
            </x-slot:header>

            @foreach($auditedEntities as $post)
                <tr class="hover:bg-[color:var(--admin-surface-raised)]/30 transition-colors">
                    <td class="py-3.5 px-4 font-bold text-[color:var(--admin-text)] max-w-xs truncate">
                        <div>{{ $post['title'] }}</div>
                        <div class="text-[10px] text-[color:var(--admin-muted)]">/{{ $post['slug'] }}</div>
                    </td>
                    <td class="py-3.5 px-4 text-[color:var(--admin-muted)]">{{ $post['type'] }}</td>
                    <td class="py-3.5 px-4 text-[color:var(--admin-text)]">
                        <x-admin.chip>{{ $post['word_count'] }} palabras</x-admin.chip>
                    </td>
                    <td class="py-3.5 px-4">
                        <x-admin.chip variant="{{ $post['score'] >= 80 ? 'success' : ($post['score'] >= 50 ? 'warning' : 'danger') }}" size="md">
                            {{ $post['score'] }}/100
                        </x-admin.chip>
                    </td>
                    <td class="py-3.5 px-4 text-[color:var(--admin-muted)]">
                        @if(empty($post['recommendations']))
                            <span class="text-[color:var(--admin-accent)]"><i class="fa-solid fa-check mr-1"></i> {{ __('admin.seo_audit.optimal_content') }}</span>
                        @else
                            <ul class="list-disc pl-4 space-y-0.5 text-[11px] text-amber-300">
                                @foreach(array_slice($post['recommendations'], 0, 2) as $rec)
                                    <li>{{ $rec }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-admin.table>
    </x-admin.form-card>

    <!-- Schema.org JSON-LD Global Graph Preview -->
    <x-admin.form-card class="space-y-3">
        <div class="flex items-center justify-between pb-2 border-b border-[color:var(--admin-border)]">
            <h3 class="font-bold text-[color:var(--admin-text)] uppercase text-xs">&gt; {{ __('admin.seo_audit.schema_graph') }}</h3>
            <span class="text-[10px] text-[color:var(--admin-accent)]">{{ __('admin.seo_audit.validated') }}</span>
        </div>
        <pre class="bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] rounded-lg p-4 text-[11px] text-[color:var(--admin-accent)] overflow-x-auto max-h-60 leading-relaxed font-mono"><code>{{ $siteSchema }}</code></pre>
    </x-admin.form-card>

</div>
@endsection
