@php
    /** Date bound (from / to): a native picker. */
    /** @var array<string, mixed> $group */
    /** @var array<string, mixed> $ui */

    $dateId = 'f-'.($group['key'] ?? 'date').'-'.uniqid();
@endphp

<label for="{{ $dateId }}" class="sr-only">{{ $group['label'] }}</label>

<input id="{{ $dateId }}"
       type="date"
       name="{{ $group['key'] }}"
       value="{{ $group['date'] ?? '' }}"
       class="{{ $ui['field'] }} cursor-pointer">
