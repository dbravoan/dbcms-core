<div data-cms-tabs data-orientation="{{ $p['orientation'] }}" data-initial-index="{{ $p['initial_index'] }}" data-responsive-mode="{{ $p['responsive_mode'] }}" data-breakpoint="{{ $p['breakpoint'] }}" class="cms-tabs">
    <div data-tab-list role="tablist" aria-label="{{ $p['label'] ?: __('Pestañas') }}" aria-orientation="{{ $p['orientation'] }}" hidden>
    @foreach($p['items'] as $index => $item)<button type="button" role="tab" id="{{ $uid }}-tab-{{ $index }}" aria-controls="{{ $uid }}-panel-{{ $index }}" aria-selected="false" tabindex="-1">{{ $item['title'] ?? '' }}</button>@endforeach
    </div>
    @foreach($p['items'] as $index => $item)<section data-tab-panel id="{{ $uid }}-panel-{{ $index }}"><h3 data-tab-fallback-title><span data-tab-static-title>{{ $item['title'] ?? '' }}</span><button type="button" data-tab-accordion-trigger id="{{ $uid }}-accordion-{{ $index }}" aria-controls="{{ $uid }}-content-{{ $index }}" aria-expanded="false" hidden>{{ $item['title'] ?? '' }}</button></h3><div data-tab-content id="{{ $uid }}-content-{{ $index }}">{!! $item['content_html'] ?? '' !!}</div></section>@endforeach
</div>
