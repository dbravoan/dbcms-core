<div data-cms-accordion data-multiple="{{ $p['multiple'] ? 'true' : 'false' }}" data-icon-style="{{ $p['icon_style'] }}" data-icon-position="{{ $p['icon_position'] }}" class="cms-accordion" @if($p['label'] !== '') aria-label="{{ $p['label'] }}" @endif>
@foreach($p['items'] as $index => $item)
    <details @if($item['open'] ?? false) open @endif><summary id="{{ $uid }}-summary-{{ $index }}"><{{ $accordionHeadingTag }} class="cms-accordion-title"><span>{{ $item['title'] ?? '' }}</span>@if($p['icon_style'] !== 'none')<span data-accordion-icon class="cms-accordion-icon" aria-hidden="true">{{ $item['icon_text'] }}</span>@endif</{{ $accordionHeadingTag }}></summary><div class="cms-accordion-panel" aria-labelledby="{{ $uid }}-summary-{{ $index }}">{!! $item['content_html'] ?? '' !!}</div></details>
@endforeach
</div>
