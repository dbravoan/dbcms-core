@props(['item', 'child' => false])

@if($item->visible)
    @if(!empty($item->children))
        <div class="admin-sidebar-item {{ $item->active ? 'is-active' : '' }}" x-data="{ expanded: @js($item->active) }">
            <button type="button" id="{{ $item->id }}" class="admin-sidebar-row admin-sidebar-toggle {{ $item->active ? 'is-active' : '' }}"
                    aria-controls="{{ $item->id }}-children" aria-expanded="{{ $item->active ? 'true' : 'false' }}"
                    :aria-expanded="expanded.toString()" x-on:click="expanded = !expanded">
                <i class="admin-sidebar-icon {{ $item->icon }}" aria-hidden="true"></i>
                <span class="admin-sidebar-label">{{ $item->label }}</span>
                @if($item->badgeText !== null)
                    <span class="admin-sidebar-badge {{ $item->badgeClass }}">{{ $item->badgeText }}</span>
                @endif
                <i class="admin-sidebar-chevron fa-solid fa-chevron-down" :class="{ 'is-expanded': expanded }" aria-hidden="true"></i>
            </button>
            <div id="{{ $item->id }}-children" class="admin-sidebar-children" x-show="expanded" @if(!$item->active) style="display: none;" @endif>
                @foreach($item->children as $entry)
                    <x-admin.sidebar-navigation-item :item="$entry" :child="true" />
                @endforeach
            </div>
        </div>
    @elseif($item->getUrl() !== null)
        <div class="admin-sidebar-item {{ $child ? 'is-child' : '' }}">
            <div class="admin-sidebar-row {{ $item->active ? 'is-active' : '' }}">
                <a id="{{ $item->id }}" href="{{ $item->getUrl() }}" class="admin-sidebar-link" @if($item->active) aria-current="page" @endif>
                    <i class="admin-sidebar-icon {{ $item->icon }}" aria-hidden="true"></i>
                    <span class="admin-sidebar-label">{{ $item->label }}</span>
                </a>
                @if($item->createRoute && \Illuminate\Support\Facades\Route::has($item->createRoute))
                    <a href="{{ route($item->createRoute) }}" title="{{ $item->createTooltip }}" aria-label="{{ $item->createTooltip ?? $item->label }}" class="admin-sidebar-create">+</a>
                @endif
                @if($item->badgeText !== null)
                    <span class="admin-sidebar-badge {{ $item->badgeClass }}">{{ $item->badgeText }}</span>
                @endif
            </div>
        </div>
    @endif
@endif
