<div class="cms-countdown" data-cms-countdown data-mode="{{ $p['mode'] }}" data-deadline="{{ $deadline }}" data-duration="{{ $p['duration'] }}" data-finished-text="{{ $p['finished_text'] }}" data-expired-action="{{ $p['expired_action'] }}" data-redirect-url="{{ $p['redirect_url'] }}" data-persistence="{{ $p['persistence'] }}" data-storage-purpose="{{ $p['storage_purpose'] }}" data-storage-enabled="{{ $countdownStorageEnabled ? 'true' : 'false' }}" data-storage-days="{{ $countdownStorageDays }}" data-instance-id="{{ $p['id'] ?? '' }}" data-definition-revision="{{ $countdownRevision }}">
@if($p['label'] !== '')<p>{{ $p['label'] }}</p>@endif
@if($deadline !== '' && $p['mode'] === 'absolute')<time datetime="{{ $deadline }}" data-countdown-fallback>{{ $deadline }}</time>@else<span data-countdown-fallback>{{ $p['duration'] }} {{ __('segundos por visita') }}</span>@endif
<span data-countdown-value role="timer" aria-live="off" hidden></span>
<span data-countdown-storage-status role="status" hidden data-message-denied="{{ __('La cuenta atrás espera tu autorización para conservar su duración entre visitas.') }}" data-message-unavailable="{{ __('No se puede conservar la cuenta atrás en este navegador. No se ha iniciado.') }}"></span>
<span data-countdown-finished role="status" hidden>{{ $p['finished_text'] }}</span>
<noscript>@if($p['finished_text'] !== '')<p>{{ $p['finished_text'] }}</p>@endif</noscript>
</div>
