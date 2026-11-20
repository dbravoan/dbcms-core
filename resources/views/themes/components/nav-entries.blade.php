@foreach($links as $link)
    @php
        $children = collect($link['children'] ?? [])->filter($withTitle);
        $url = (string) ($link['url'] ?? '#');
        $blank = (bool) ($link['target_blank'] ?? $link['blank'] ?? false);
    @endphp
    @if($children->isNotEmpty())
        <details class="cms-nav-dropdown">
            <summary class="{{ $linkClass ?? '' }}">{{ $link['title'] }}</summary>
            <div class="cms-nav-submenu">
                @if($url !== '' && $url !== '#')
                    <a href="{{ $url }}"@if($blank) target="_blank" rel="noopener"@endif class="{{ $linkClass ?? '' }}">{{ $link['title'] }}</a>
                @endif
                @include('themes::components.nav-entries', ['links' => $children])
            </div>
        </details>
    @else
        <a href="{{ $url }}"@if($blank) target="_blank" rel="noopener"@endif class="{{ $linkClass ?? '' }}">{{ $link['title'] }}</a>
    @endif
@endforeach
