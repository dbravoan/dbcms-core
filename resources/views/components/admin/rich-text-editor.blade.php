@props(['label', 'source' => null, 'onUpdate' => null, 'when' => 'true', 'name' => null, 'value' => null])

<div data-rich-text-editor
     x-data="richTextEditor(@js($label))"
     x-on:rich-text-change.stop="{{ $onUpdate ?? 'formSource = $event.detail; $refs.format.value = formSource.format; $refs.content.value = formSource.content' }}">
    @if($name !== null)
        <p class="admin-field-label">{{ $label }}</p>
        <input type="hidden" name="{{ $name }}[format]" value="{{ $value['format'] ?? 'html' }}" x-ref="format"
               {{ $attributes->except("data-blade-field")->merge(['data-blade-field' => $attributes->get('data-blade-field').'.format']) }}>
        <textarea hidden name="{{ $name }}[content]" x-ref="content"
                  x-on:change="formSource = {format: $refs.format.value, content: $el.value}"
                  {{ $attributes->except('data-blade-field') }} data-blade-field="{{ $attributes->get('data-blade-field') }}.content">{{ $value['content'] ?? '' }}</textarea>
    @endif
    <div x-ref="editor" x-effect="if ({{ $when }}) refresh($el, () => {{ $source ?? '(formSource ?? {format: $refs.format.value, content: $refs.content.value})' }}, value => $dispatch('rich-text-change', value))"></div>
</div>
