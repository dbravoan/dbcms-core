@php
    /** Single-choice control (sort, period…): a real <select> that submits on change. */
    /** @var array<string, mixed> $group */
    /** @var array<string, mixed> $ui */

    $selectId = 'f-'.($group['key'] ?? 'select').'-'.uniqid();
    $current = $group['current'] ?? null;
@endphp

<label for="{{ $selectId }}" class="sr-only">{{ $group['label'] }}</label>

<div class="relative">
    <select id="{{ $selectId }}"
            name="{{ $group['key'] }}"
            data-auto-submit
            class="{{ $ui['field'] }} cursor-pointer appearance-none pr-8">
        <option value="">{{ __('Todas las opciones') }}</option>
        @foreach($group['values'] as $value)
            <option value="{{ $value['value'] }}" @selected($current === $value['value'])>
                {{ $value['label'] }}
            </option>
        @endforeach
    </select>

    <i class="fa-solid fa-chevron-down pointer-events-none absolute inset-y-0 right-3 my-auto h-3 text-[10px] text-current opacity-50"
       aria-hidden="true"></i>
</div>
