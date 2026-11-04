@php
    /** Boolean flag: a real checkbox that submits as soon as it changes. */
    /** @var array<string, mixed> $group */
    /** @var array<string, mixed> $ui */

    $switchId = 'f-'.($group['key'] ?? 'switch').'-'.uniqid();
@endphp

<label for="{{ $switchId }}" class="{{ $ui['switchRow'] }}">
    <input id="{{ $switchId }}"
           type="checkbox"
           name="{{ $group['key'] }}"
           value="1"
           data-auto-submit
           @checked($group['checked'] ?? false)
           class="{{ $ui['switchInput'] }}">
    <span>{{ $group['label'] }}</span>
</label>
