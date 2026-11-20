@props([
    'schema' => [],
    'variant' => 'cyber',
    'layout' => 'grid',
    'honeypot' => null,
])

@php
    $registry = app(\DbCms\Core\Forms\FieldTypeRegistry::class);
    $container = $layout === 'grid' ? 'grid grid-cols-1 sm:grid-cols-2 gap-4' : 'space-y-4';
@endphp

<div class="{{ $container }}">
    @if($honeypot)
        <div style="display:none !important;" aria-hidden="true">
            <input type="text" name="{{ $honeypot }}" tabindex="-1" autocomplete="off">
        </div>
    @endif

    @foreach($schema as $field)
        @php($type = $registry->resolve($field->type))

        @if($type !== null)
            @include($type->view(), [
                'field' => $field,
                'variant' => $variant,
                'wrapperClass' => ($layout === 'grid' && $field->isWide()) ? 'sm:col-span-2' : '',
            ])
        @endif
    @endforeach
</div>
