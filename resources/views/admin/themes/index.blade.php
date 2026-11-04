@extends('admin.layout')

@section('title', __('admin.page_titles.themes'))

@section('content')
<div class="admin-page space-y-6 font-mono text-xs">
    <div>
        <h1 class="admin-page-title">
            <span>&gt;</span> {{ __('admin.themes.manager_title') }}
        </h1>
        <p class="text-[color:var(--admin-muted)] mt-1">
            {{ __('admin.themes.manager_subtitle') }} <span class="text-[color:var(--admin-accent)] font-bold">{{ $activeTheme }}</span>.
        </p>
    </div>

    <!-- Themes Grid -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        @foreach($themes as $identifier => $theme)
            <x-admin.form-card class="flex flex-col justify-between space-y-4 {{ $identifier === $activeTheme ? 'border-[color:var(--admin-accent)] shadow-sm' : '' }}">
                <div>
                    <!-- Theme Header Badge -->
                    <div class="flex items-center justify-between">
                        <span class="text-base font-bold text-[color:var(--admin-text)]">{{ $theme['name'] ?? ucfirst($identifier) }}</span>
                        @if($identifier === $activeTheme)
                            <span class="px-2 py-0.5 rounded bg-[color:var(--admin-accent)]/10 text-[color:var(--admin-accent)] border border-[color:var(--admin-accent)] font-bold text-[10px]">
                                {{ __('admin.themes.active') }}
                            </span>
                        @else
                            <span class="text-[color:var(--admin-muted)] text-[10px]">v{{ $theme['version'] ?? '1.0.0' }}</span>
                        @endif
                    </div>

                    <p class="text-[color:var(--admin-muted)] text-xs mt-2 leading-relaxed">
                        {{ $theme['description'] ?? __('admin.themes.default_description') }}
                    </p>

                    <div class="mt-4 p-3 bg-[color:var(--admin-surface)] rounded border border-[color:var(--admin-border)] text-[11px] text-[color:var(--admin-muted)] space-y-1">
                        <div>{{ __('admin.themes.directory') }}: <code class="text-[color:var(--admin-text)]">themes/{{ $identifier }}/</code></div>
                        <div>{{ __('admin.themes.author') }}: <span class="text-[color:var(--admin-text)]">{{ $theme['author'] ?? '—' }}</span></div>
                    </div>
                </div>

                <x-admin.form-actions :submit="false" class="pt-4 border-t border-[color:var(--admin-border)] flex items-center justify-between">
                    @if($identifier === $activeTheme)
                        <button type="button" disabled class="px-4 py-2 bg-[color:var(--admin-accent)]/20 text-[color:var(--admin-accent)] border border-[color:var(--admin-accent)]/40 rounded font-bold cursor-default">
                            <i class="fa-solid fa-check mr-1"></i> {{ __('admin.themes.current') }}
                        </button>
                    @else
                        <form method="POST" action="{{ route('admin.themes.activate', $identifier) }}">
                            @csrf
                            <x-admin.button variant="primary" type="submit" class="">
                                {{ __('admin.themes.activate') }} &rarr;
                            </x-admin.button>
                        </form>
                    @endif
                </x-admin.form-actions>
            </x-admin.form-card>
        @endforeach
    </div>
</div>
@endsection
