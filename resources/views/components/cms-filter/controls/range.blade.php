@php
    /** Range (price, surface…): two numeric fields with the localized unit. */
    /** @var array<string, mixed> $group */
    /** @var array<string, mixed> $ui */

    $minId = 'f-'.($group['key'] ?? 'range').'-min-'.uniqid();
    $maxId = 'f-'.($group['key'] ?? 'range').'-max-'.uniqid();
@endphp

<div class="{{ $ui['numberRow'] }}">
    <label for="{{ $minId }}" class="sr-only">{{ $group['label'] }} · {{ __('Mínimo') }}</label>
    <input id="{{ $minId }}"
           type="number"
           inputmode="numeric"
           min="0"
           name="{{ $group['key'] }}_min"
           value="{{ $group['range']['min'] ?? '' }}"
           placeholder="{{ __('Mínimo') }}"
           class="{{ $ui['field'] }} {{ $ui['numberInput'] }}">

    <span class="{{ $ui['dash'] }}" aria-hidden="true">–</span>

    <label for="{{ $maxId }}" class="sr-only">{{ $group['label'] }} · {{ __('Máximo') }}</label>
    <input id="{{ $maxId }}"
           type="number"
           inputmode="numeric"
           min="0"
           name="{{ $group['key'] }}_max"
           value="{{ $group['range']['max'] ?? '' }}"
           placeholder="{{ __('Máximo') }}"
           class="{{ $ui['field'] }} {{ $ui['numberInput'] }}">

    @if(($group['unit'] ?? '') !== '')
        <span class="{{ $ui['unit'] }}">{{ $group['unit'] }}</span>
    @endif
</div>
