@props(['blockKey' => 'block', 'dynamicPayloads' => [], 'isSubBlock' => false, 'colIdx' => null, 'subIdx' => null])
@php($b = $blockKey)
<div class="admin-block-fields space-y-4">
    <template x-if="!isBlockAvailable({{ $b }})">
        <div role="status" class="rounded-lg border border-amber-500/40 bg-amber-500/10 p-4 text-sm">
            <strong>Bloque no disponible</strong>
            <p>Su contenido permanece guardado. Reactiva su paquete para editarlo y mostrarlo.</p>
            <span class="font-mono text-xs" x-text="{{ $b }}.type"></span>
        </div>
    </template>
    <template x-if="isBlockAvailable({{ $b }}) && !typeDef({{ $b }}.type).editorView">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <template x-for="field in schemaFields({{ $b }})" :key="field.key">
                <template x-if="field.type === 'repeater'">
                    <div class="admin-field md:col-span-2" x-show="fieldVisible({{ $b }}, field)">
                        <span :id="'block-field-' + {{ $b }}.id + '-' + field.key + '-label'" class="admin-field-label" x-text="field.label || field.key"></span>
                        <div class="space-y-3" role="group" :aria-labelledby="'block-field-' + {{ $b }}.id + '-' + field.key + '-label'">
                            <template x-for="(item, itemIndex) in ({{ $b }}[field.key] || [])" :key="itemIndex">
                                <div class="admin-block-repeater-item rounded-lg border border-[color:var(--admin-border)] p-3 space-y-3">
                                    <template x-for="(child, childKey) in field.fields" :key="childKey">
                                        @include('components.admin.blocks._schema_field', [
                                            'model' => 'item',
                                            'keyExpr' => 'childKey',
                                            'fieldVar' => 'child',
                                            'blockKey' => $b,
                                            'idExpr' => "'block-field-' + ".$b.".id + '-' + field.key + '-' + itemIndex + '-' + childKey",
                                            'isChild' => true,
                                        ])
                                    </template>
                                    <x-admin.button type="button" variant="ghost" size="sm" @click="{{ $b }}[field.key].splice(itemIndex, 1); syncBlocksToLocale()">Eliminar elemento</x-admin.button>
                                </div>
                            </template>
                            <x-admin.button type="button" variant="secondary" size="sm" @click="addSchemaItem({{ $b }}, field)">Añadir elemento</x-admin.button>
                        </div>
                        <span :id="'block-field-' + {{ $b }}.id + '-' + field.key + '-hint'" x-show="field.description" x-text="field.description" class="admin-field-hint"></span>
                    </div>
                </template>
                <template x-if="field.type !== 'repeater'">
                    @include('components.admin.blocks._schema_field', [
                        'model' => $b,
                        'keyExpr' => 'field.key',
                        'fieldVar' => 'field',
                        'blockKey' => $b,
                        'idExpr' => "'block-field-' + ".$b.".id + '-' + field.key",
                    ])
                </template>
            </template>
        </div>
    </template>
    @foreach($activeBlockTypes as $definition)
        @if($definition->getEditorView())
            <template x-if="{{ $b }}.type === @js($definition->getType())">
                <div>@include($definition->getEditorView(), ['blockKey' => $b])</div>
            </template>
        @endif
    @endforeach
</div>
