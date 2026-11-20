@if($p['label'] !== '')
@if($buttonAction === 'open_overlay' && $buttonOverlay !== '')<button type="button" data-cms-overlay-open="{{ $buttonOverlay }}" aria-haspopup="dialog" class="cms-btn cms-btn-{{ $buttonVariant }}">{{ $p['label'] }}</button>
@elseif($buttonAction === 'close_overlay')<button type="button" data-cms-overlay-close="{{ $buttonOverlay }}" class="cms-btn cms-btn-{{ $buttonVariant }}">{{ $p['label'] }}</button>
@elseif($buttonAction === 'link' && $p['url'] !== '')<a href="{{ $p['url'] }}" class="cms-btn cms-btn-{{ $buttonVariant }}" @if($p['new_window']) target="_blank" rel="noopener noreferrer" @endif>{{ $p['label'] }}@if($p['new_window'])<span class="sr-only"> ({{ __('Se abre en otra pestaña') }})</span>@endif</a>@endif
@endif
