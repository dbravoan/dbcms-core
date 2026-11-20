<button type="button" data-cms-overlay-open="{{ $overlayId }}" class="cms-btn cms-btn-secondary" aria-controls="{{ $uid }}" aria-haspopup="dialog">{{ $p['trigger_label'] ?: __('Abrir panel') }}</button>
<div id="{{ $uid }}" data-cms-overlay data-overlay-id="{{ $overlayId }}" @if($overlayAlias !== '') data-overlay-alias="{{ $overlayAlias }}" @endif data-overlay-modal="{{ $p['modal'] ? 'true' : 'false' }}" data-overlay-backdrop-close="{{ $p['backdrop_close'] ? 'true' : 'false' }}" class="cms-drawer cms-drawer-{{ $drawerSide }} {{ $p['backdrop'] ? '' : 'cms-drawer-no-backdrop' }}" role="dialog" aria-labelledby="{{ $uid }}-title" tabindex="-1" hidden>
    <div class="cms-drawer-panel" data-cms-presentation="drawer" @foreach($drawerMotionAttributes as $name => $value) {{ $name }}="{{ $value }}" @endforeach>
        {!! $drawerMediaHtml !!}
        <div data-cms-presentation-content data-cms-presentation-entrance>
            <h2 id="{{ $uid }}-title">{{ $p['title'] ?: __('Panel lateral') }}</h2><button type="button" data-cms-overlay-close aria-label="{{ __('Cerrar panel') }}">×</button>{!! $childrenHtml !!}
        </div>
    </div>
</div>
<noscript><section><h2>{{ $p['title'] }}</h2>{!! $childrenHtml !!}</section></noscript>
