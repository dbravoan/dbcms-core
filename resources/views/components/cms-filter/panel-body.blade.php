@php
    /** Body of the filter panel: accent, form, groups, hidden state, actions. */
    /** @var array<string, mixed> $panel */
    /** @var array<string, mixed> $ui */
    $controlPartials = [
        'checkboxes' => 'terms',
        'radios' => 'terms',
        'select' => 'select',
        'range' => 'range',
        'date' => 'date',
        'search' => 'search',
        'switch' => 'switch',
    ];
@endphp

<div class="{{ $ui['bodyWrap'] }}">
    @if(($ui['accent'] ?? '') !== '')
        <div class="{{ $ui['accent'] }}" aria-hidden="true"></div>
    @endif

    <form method="GET" action="{{ $panel['action'] }}" class="{{ $ui['form'] }}" data-filter-form>
        {{-- Selections made through links (terms) must survive a form submit. --}}
        @foreach($panel['hidden'] as $name => $values)
            @foreach($values as $value)
                <input type="hidden" name="{{ $name }}[]" value="{{ $value }}">
            @endforeach
        @endforeach

        <div class="{{ $ui['groupsWrap'] }}">
            @foreach($panel['groups'] as $group)
                <fieldset class="{{ $ui['group'] }}" data-filter-group="{{ $group['key'] }}">
                    <legend class="{{ $ui['legend'] }}">
                        @if(($ui['legendPrefix'] ?? '') !== '')
                            <span class="{{ $ui['legendPrefixClass'] ?? '' }}" aria-hidden="true">{{ $ui['legendPrefix'] }}</span>
                        @endif
                        {{ $group['label'] }}
                    </legend>

                    @include('components.cms-filter.controls.'.($controlPartials[$group['control']] ?? 'terms'), [
                        'group' => $group,
                        'ui' => $ui,
                    ])
                </fieldset>
            @endforeach
        </div>

        <div class="{{ $ui['submitWrap'] }}">
            <button type="submit" class="{{ $ui['submit'] }}">
                <i class="fa-solid fa-filter {{ $ui['submitIcon'] }}" aria-hidden="true"></i>
                <span>{{ __('Aplicar filtros') }}</span>
            </button>

            @if($panel['clearUrl'])
                <a href="{{ $panel['clearUrl'] }}" class="{{ $ui['clearAll'] }}">{{ __('Limpiar todo') }}</a>
            @endif
        </div>
    </form>
</div>
