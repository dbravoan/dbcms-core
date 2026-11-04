@php
    /** Free-text filter (usually the search fallback): Enter submits, no JS needed. */
    /** @var array<string, mixed> $group */
    /** @var array<string, mixed> $ui */

    $searchId = 'f-'.($group['key'] ?? 'search').'-'.uniqid();
@endphp

<label for="{{ $searchId }}" class="sr-only">{{ $group['label'] }}</label>

<div class="relative">
    <input id="{{ $searchId }}"
           type="search"
           name="{{ $group['key'] }}"
           value="{{ $group['term'] ?? '' }}"
           placeholder="{{ __('Escribe para filtrar…') }}"
           class="{{ $ui['field'] }} pr-9">

    <i class="fa-solid fa-magnifying-glass pointer-events-none absolute inset-y-0 right-3 my-auto h-3 text-[11px] opacity-50"
       aria-hidden="true"></i>
</div>
