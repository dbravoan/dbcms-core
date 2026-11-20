<div data-cms-alert class="cms-alert cms-alert-{{ $alertTone }}" role="note" @if($p['label'] !== '') aria-label="{{ $p['label'] }}" @endif>
    <div class="cms-alert-body">{!! $alertHtml !!}</div>
    @if($p['dismissible'])<button type="button" data-alert-close hidden aria-label="{{ __('Cerrar aviso') }}">×</button>@endif
</div>
