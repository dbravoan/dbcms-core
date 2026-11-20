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

<div {{ $attributes->merge(['class' => 'space-y-4']) }}>
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
    <form method="GET" class="admin-list-toolbar">
        <!-- Preserve existing sort params -->
        @if(request('sort_by'))
            <input type="hidden" name="sort_by" value="{{ request('sort_by') }}">
        @endif
        @if(request('sort_dir'))
            <input type="hidden" name="sort_dir" value="{{ request('sort_dir') }}">
        @endif

        <div class="admin-list-filters">
            <!-- Live Text Search Input -->
            <div class="admin-list-search">
                <span class="admin-list-search-icon" aria-hidden="true"><i class="fa-solid fa-magnifying-glass"></i></span>
                <input type="text"
                       name="search"
                       value="{{ request('search') }}"
                       placeholder="{{ $searchPlaceholder }}"
                       class="admin-control admin-control--leading-icon admin-control--trailing-icon" aria-label="{{ $searchPlaceholder }}">
                @if(request('search'))
                    <a href="{{ request()->fullUrlWithQuery(['search' => null, 'page' => 1]) }}"
                       class="admin-list-search-clear"
                       aria-label="{{ __('admin.actions.clear') }}" title="{{ __('admin.actions.clear') }}"><i class="fa-solid fa-xmark" aria-hidden="true"></i></a>
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
                <a href="{{ url()->current() }}" class="admin-link">
                    {{ __('admin.actions.clear') }}
                </a>
            @endif
        </div>

        <!-- Per Page Limit Selector -->
        <label class="admin-list-limit">
            <span>{{ __('admin.table.show') }}</span>
            <select name="per_page" data-auto-submit class="admin-control" aria-label="{{ __('admin.table.show') }}">
                @foreach(config('cms.options.per_page') as $perPage)
                    <option value="{{ $perPage }}" {{ (string) request('per_page', '15') === (string) $perPage ? 'selected' : '' }}>{{ $perPage }}</option>
                @endforeach
            </select>
        </label>
    </form>
</div>
