@php
    /** A single toggleable facet value (link + affordance indicator). */
    /** @var array<string, mixed> $value */
    /** @var array<string, mixed> $ui */

    $active = (bool) ($value['active'] ?? false);
    $iconMode = (string) ($iconMode ?? 'check');
@endphp

<a href="{{ $value['url'] }}"
   class="{{ $active ? $ui['optionActive'] : $ui['option'] }}"
   aria-pressed="{{ $active ? 'true' : 'false' }}"
   data-filter-option="{{ $value['value'] ?? '' }}">
    @if($iconMode !== 'none')
        @if($isMulti)
            <span class="{{ $ui['box'] }}" aria-hidden="true">
                @if($active)
                    <i class="fa-solid fa-check {{ $ui['boxCheck'] }}"></i>
                @endif
            </span>
        @else
            <span class="{{ $ui['radio'] }}" aria-hidden="true">
                @if($active)
                    <span class="{{ $ui['radioDot'] }}"></span>
                @endif
            </span>
        @endif
    @endif

    <span>{{ $value['label'] }}</span>
</a>
