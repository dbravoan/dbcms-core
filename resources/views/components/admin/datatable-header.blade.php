@props([
    'title' => '',
    'description' => '',
    'searchPlaceholder' => null,
    'createRoute' => null,
    'createLabel' => null,
    'createModalEvent' => null,
])

@php
    $searchPlaceholder = $searchPlaceholder ?? __('admin.table.search_placeholder');
    $createLabel = $createLabel ?? '+ '.__('admin.actions.create');
@endphp

<div class="space-y-4 font-mono text-xs">
    <x-admin.form-header :title="$title" :description="$description">
        <x-slot:actions>
            @isset($actions){{ $actions }}@endisset
            @if(!empty($createRoute))
                <x-admin.button :href="$createRoute" variant="primary">{{ $createLabel }}</x-admin.button>
            @elseif(!empty($createModalEvent))
                <x-admin.button type="button" variant="primary" @click="$dispatch('{{ $createModalEvent }}')">{{ $createLabel }}</x-admin.button>
            @endif
        </x-slot:actions>
    </x-admin.form-header>

    <!-- Toolbar: Search, Filters & Per-Page Controls -->
    <form method="GET" class="admin-card p-4 flex flex-wrap items-center justify-between gap-4">
        <!-- Preserve existing sort params -->
        @if(request('sort_by'))
            <input type="hidden" name="sort_by" value="{{ request('sort_by') }}">
        @endif
        @if(request('sort_dir'))
            <input type="hidden" name="sort_dir" value="{{ request('sort_dir') }}">
        @endif

        <div class="flex flex-wrap items-center gap-3 flex-grow">
            <!-- Live Text Search Input -->
            <div class="relative min-w-0 w-full sm:w-auto sm:min-w-[240px] max-w-sm flex-grow">
                <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-[color:var(--admin-muted)]"><i class="fa-solid fa-magnifying-glass"></i></span>
                <input type="text"
                       name="search"
                       value="{{ request('search') }}"
                       placeholder="{{ $searchPlaceholder }}"
                       class="admin-control admin-control--leading-icon admin-control--trailing-icon" aria-label="{{ $searchPlaceholder }}">
                @if(request('search'))
                    <a href="{{ request()->fullUrlWithQuery(['search' => null, 'page' => 1]) }}"
                       class="absolute inset-y-0 right-0 pr-2.5 flex items-center text-[color:var(--admin-muted)] hover:text-[color:var(--admin-text)]">&times;</a>
                @endif
            </div>

            <!-- Extra Custom Filter Dropdowns Slot -->
            @isset($slot)
                {{ $slot }}
            @endisset

            <x-admin.button type="submit" variant="neutral" icon="fa-solid fa-filter">
                {{ __('admin.actions.filter') }}
            </x-admin.button>

            @if(request()->hasAny(['search', 'status', 'role', 'category_id', 'market_id', 'is_active', 'is_published']))
                <a href="{{ url()->current() }}" class="text-[color:var(--admin-muted)] hover:text-red-400 text-xs underline">
                    {{ __('admin.actions.clear') }}
                </a>
            @endif
        </div>

        <!-- Per Page Limit Selector -->
        <div class="flex items-center gap-2 text-[color:var(--admin-muted)]">
            <span class="text-[11px] uppercase">{{ __('admin.table.show') }}</span>
            <select name="per_page" data-auto-submit class="admin-control" aria-label="{{ __('admin.table.show') }}">
                @foreach(config('cms.options.per_page') as $perPage)
                    <option value="{{ $perPage }}" {{ (string) request('per_page', '15') === (string) $perPage ? 'selected' : '' }}>{{ $perPage }}</option>
                @endforeach
            </select>
        </div>
    </form>
</div>
