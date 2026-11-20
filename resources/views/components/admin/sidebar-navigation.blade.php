@props(['sections' => []])

<nav {{ $attributes->class(['admin-sidebar-nav']) }} aria-label="{{ __('admin.nav.label') }}">
    @foreach($sections as $section)
        @if($section->visible && !empty($section->items))
            <div class="admin-sidebar-section">
                <div class="admin-sidebar-section-title">{{ $section->title }}</div>
                @foreach($section->items as $item)
                    <x-admin.sidebar-navigation-item :item="$item" />
                @endforeach
            </div>
        @endif
    @endforeach
</nav>
