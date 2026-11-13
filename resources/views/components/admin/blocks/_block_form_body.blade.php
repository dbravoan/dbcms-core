@props(['blockKey' => 'block', 'dynamicPayloads' => [], 'isSubBlock' => false, 'colIdx' => null, 'subIdx' => null])
@php
    $b = $blockKey;
    // IDs belong to the rendered editor, not the persisted block payload.
    // Older nested blocks can have no ID, and cloned blocks can share one.
    $fieldPrefix = $isSubBlock
        ? "'block-field-' + (block.id || index) + '-' + index + '-col-' + colIdx + '-sub-' + subIdx"
        : "'block-field-' + (block.id || index) + '-' + index + '-root'";
@endphp
<div class="admin-block-fields space-y-4">
    <template x-if="!isBlockAvailable({{ $b }})">
        <div role="status" class="rounded-lg border border-amber-500/40 bg-amber-500/10 p-4 text-sm">
            <strong x-text="isRetiredBlockPayload({{ $b }}) ? 'Formato de bloque retirado' : 'Bloque no disponible'"></strong>
            <p x-text="unavailableBlockMessage({{ $b }})"></p>
            <span class="font-mono text-xs" x-text="{{ $b }}.type"></span>
        </div>
    </template>
    <template x-if="isBlockAvailable({{ $b }}) && !typeDef({{ $b }}.type).editorView">
        <div class="grid grid-cols-1 gap-4" x-effect="mountSchemaEditor($el, {{ $b }})" data-schema-editor></div>
    </template>
    @foreach($activeBlockTypes as $definition)
        @if($definition->getEditorView())
            <template x-if="{{ $b }}.type === @js($definition->getType())">
                <div>@include($definition->getEditorView(), ['blockKey' => $b])</div>
            </template>
        @endif
    @endforeach
</div>
