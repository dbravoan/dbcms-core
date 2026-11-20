{{-- Barra del lienzo: historial y estructura opcional. Misma familia admin-btn que el visor. --}}
<section class="cms-inspector-workspace cms-canvas-toolbar-host" aria-label="Editor de secciones">
    <div class="cms-inspector-toolbar">
        <x-admin.button variant="ghost" size="sm" @click="showStructure = !showStructure" alpine-active="showStructure" x-bind:aria-expanded="showStructure" aria-controls="cms-structure-panel">{{ __('admin.block_manager.structure') }}</x-admin.button>
        <x-admin.button variant="ghost" size="sm" @click="undo()" x-bind:disabled="!canUndo()" icon="fa-solid fa-rotate-left">{{ __('admin.block_manager.undo') }}</x-admin.button>
        <x-admin.button variant="ghost" size="sm" @click="redo()" x-bind:disabled="!canRedo()" icon="fa-solid fa-rotate-right">{{ __('admin.block_manager.redo') }}</x-admin.button>
        <x-admin.button variant="ghost" size="sm" data-open-preview @click="openPreview()" x-bind:disabled="!editorOptions.previewUrl || blocks.length === 0" icon="fa-solid fa-eye">{{ __('admin.block_manager.preview_open') }}</x-admin.button>
        <x-admin.button variant="primary" size="sm" @click="addEmptySection(null)" icon="fa-solid fa-plus">{{ __('admin.block_manager.add_section') }}</x-admin.button>
    </div>
    <nav id="cms-structure-panel" x-show="showStructure" x-cloak aria-label="{{ __('admin.block_manager.structure_label') }}" class="cms-inspector-tree">
        <p x-show="blocks.length === 0" class="cms-inspector-empty">{{ __('admin.block_manager.structure_empty') }}</p>
        <template x-for="row in inspectorTreeRows()" :key="row.kind + JSON.stringify(row.path)">
            <button type="button" @click="row.kind === 'column' ? revealColumn(row.path) : revealNode(row.path)" :aria-pressed="row.kind === 'column' ? inspectorColumnId === row.node.id : selectedNode() === row.node && !inspectorColumn()" :style="'padding-inline-start:' + (row.depth * 14 + 8) + 'px'" class="cms-inspector-tree-child"><span x-text="row.name"></span><small x-show="row.kind !== 'column' && row.node.title" x-text="' · ' + row.node.title"></small><small x-show="!row.available"> · {{ __('admin.block_manager.unavailable_short') }}</small></button>
        </template>
    </nav>
    @include('components.admin.blocks.inspector.preview')
    @include('components.admin.blocks.inspector.design')
    @include('components.admin.blocks.inspector.motion')
</section>
