@php
    /**
     * Terms (multi = checkboxes, single = radios): crawlable links that toggle a
     * value, so multi-selection accumulates in the canonical URL with no
     * JavaScript and every facet keeps a real, shareable address.
     */
    /** @var array<string, mixed> $group */
    /** @var array<string, mixed> $ui */

    $isMulti = ($group['control'] ?? 'checkboxes') === 'checkboxes';
    $values = (array) ($group['values'] ?? []);
    $maxVisible = max(4, (int) ($ui['maxVisible'] ?? 12));
    $visible = array_slice($values, 0, $maxVisible);
    $rest = array_slice($values, $maxVisible);
    $iconMode = (string) ($ui['icon'] ?? 'check');
@endphp

<div class="{{ $ui['options'] }}">
    @foreach($visible as $value)
        @include('components.cms-filter.controls.option', ['value' => $value, 'isMulti' => $isMulti, 'ui' => $ui, 'iconMode' => $iconMode])
    @endforeach
</div>

@if($rest !== [])
    <details class="{{ $ui['moreWrap'] }}" data-filter-more>
        <summary class="{{ $ui['moreSummary'] }}">
            {{ __('Ver todas (:count)', ['count' => count($values)]) }}
        </summary>

        <div class="{{ $ui['options'] }} mt-2">
            @foreach($rest as $value)
                @include('components.cms-filter.controls.option', ['value' => $value, 'isMulti' => $isMulti, 'ui' => $ui, 'iconMode' => $iconMode])
            @endforeach
        </div>
    </details>
@endif
