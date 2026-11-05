@php
    $ui = \DbCms\Core\Forms\FormUi::classes($variant ?? 'cyber');
    $id = $field->htmlId();
    $old = old($field->key);
    $required = $field->required;
    $type = $field->type;
    $inputType = match ($type) {
        'email' => 'email',
        'tel' => 'tel',
        'number' => 'number',
        default => 'text',
    };
@endphp

<div class="{{ trim(($wrapperClass ?? '').' space-y-1') }}">
    @if($type === 'checkbox')
        <div class="pt-1">
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox"
                       id="{{ $id }}"
                       name="{{ $field->key }}"
                       value="1"
                       {{ $required ? 'required' : '' }}
                       {{ $old ? 'checked' : '' }}
                       class="{{ $ui['checkbox'] }}">
                <span class="{{ $ui['help'] }}">{{ $field->placeholder ?: $field->label() }}</span>
            </label>
        </div>
    @else
        <label for="{{ $id }}" class="{{ $ui['label'] }}">
            {{ $field->label() }}
            @if($required)
                <span class="{{ $ui['star'] }}">*</span>
            @endif
        </label>

        @if($type === 'textarea')
            <textarea id="{{ $id }}"
                      name="{{ $field->key }}"
                      rows="4"
                      {{ $required ? 'required' : '' }}
                      placeholder="{{ $field->placeholder }}"
                      class="{{ $ui['input'] }}">{{ $old }}</textarea>
        @elseif($type === 'select')
            <select id="{{ $id }}" name="{{ $field->key }}" {{ $required ? 'required' : '' }} class="{{ $ui['input'] }} cursor-pointer">
                <option value="">{{ $field->placeholder ?: __('Selecciona una opción...') }}</option>
                @foreach($field->options as $option)
                    <option value="{{ $option['value'] }}" {{ (string) $old === (string) $option['value'] ? 'selected' : '' }}>{{ $option['label'] }}</option>
                @endforeach
            </select>
        @elseif($type === 'radio')
            <div class="space-y-1">
                @foreach($field->options as $option)
                    <label class="flex items-center gap-2 cursor-pointer {{ $ui['help'] }}">
                        <input type="radio"
                               name="{{ $field->key }}"
                               value="{{ $option['value'] }}"
                               {{ $required ? 'required' : '' }}
                               {{ (string) $old === (string) $option['value'] ? 'checked' : '' }}
                               class="{{ $ui['checkbox'] }}">
                        <span>{{ $option['label'] }}</span>
                    </label>
                @endforeach
            </div>
        @else
            <input type="{{ $inputType }}"
                   id="{{ $id }}"
                   name="{{ $field->key }}"
                   value="{{ $old }}"
                   {{ $required ? 'required' : '' }}
                   placeholder="{{ $field->placeholder }}"
                   class="{{ $ui['input'] }}">
        @endif
    @endif

    @if(isset($errors) && $errors->has($field->key))
        <p class="{{ $ui['error'] }}">{{ $errors->first($field->key) }}</p>
    @endif
</div>
