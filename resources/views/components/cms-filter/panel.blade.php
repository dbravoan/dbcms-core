@php
    /**
     * Shared filter panel: one accessible, canonical, no-JS-first implementation
     * that every front theme skins with its own tokens (`$ui`).
     *
     * - Options are crawlable links that toggle values (multi-select accumulates).
     * - Free-form controls (range, date, search, select, switch) live in a GET
     *   form whose submission is canonicalized with a 301 by the core.
     * - Link-based selections travel as hidden inputs, so nothing is lost.
     * - On mobile the panel collapses (no JavaScript); on desktop it is always
     *   open, and the apply button sticks to the bottom of the viewport.
     */
    /** @var array<string, mixed> $panel */
    /** @var array<string, mixed> $ui */
    /** @var string $theme */

    $activeCount = (int) ($panel['activeCount'] ?? 0);
    $chips = (array) ($panel['chips'] ?? []);
    $groups = (array) ($panel['groups'] ?? []);
    $collapsible = (bool) ($ui['collapse'] ?? true);
@endphp

<div class="cms-filter-widget" data-filter-panel="{{ $theme }}">
    {{-- Active filters: every chip is a crawlable link that removes one value. --}}
    @if($chips !== [])
        <div class="{{ $ui['chipsWrap'] }}" aria-label="{{ __('Filtros activos') }}">
            @foreach($chips as $chip)
                <a href="{{ $chip['url'] }}" class="{{ $ui['chip'] }}" rel="nofollow">
                    <span>{{ $chip['label'] }}</span>
                    <i class="fa-solid fa-xmark {{ $ui['chipRemove'] }}" aria-hidden="true"></i>
                    <span class="sr-only">{{ __('Quitar filtro') }}</span>
                </a>
            @endforeach

            @if($panel['clearUrl'])
                <a href="{{ $panel['clearUrl'] }}" class="{{ $ui['clearAll'] }}">{{ __('Limpiar todo') }}</a>
            @endif
        </div>
    @endif

    @if($collapsible)
        {{--
            Open by default so the filters are always reachable without
            JavaScript; `app.js` folds it on small screens (where the summary
            row takes over), which keeps the mobile page tidy without hiding
            anything from crawlers or no-JS visitors.
        --}}
        <details class="group {{ $ui['panel'] }}" open data-filter-collapsible>
            <summary class="{{ $ui['summary'] }}">
                <span class="inline-flex items-center gap-2">
                    <i class="fa-solid fa-sliders" aria-hidden="true"></i>
                    <span class="{{ $ui['summaryTitle'] }}">{{ __('Filtros') }}</span>
                    <span class="{{ $ui['summaryBadge'] }}" aria-live="polite">{{ $activeCount }}</span>
                </span>
                <i class="fa-solid fa-chevron-down {{ $ui['summaryChevron'] }} group-open:rotate-180" aria-hidden="true"></i>
            </summary>

            @include('components.cms-filter.panel-body')
        </details>
    @else
        <section class="{{ $ui['panel'] }}">
            <header class="{{ $ui['summary'] }} cursor-default">
                <span class="inline-flex items-center gap-2">
                    <i class="fa-solid fa-sliders" aria-hidden="true"></i>
                    <span class="{{ $ui['summaryTitle'] }}">{{ __('Filtros') }}</span>
                    @if($activeCount > 0)
                        <span class="{{ $ui['summaryBadge'] }}" aria-live="polite">{{ $activeCount }}</span>
                    @endif
                </span>
            </header>

            @include('components.cms-filter.panel-body')
        </section>
    @endif
</div>
