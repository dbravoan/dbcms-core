<section class="cms-inspector-workspace" aria-label="Editor de composición">
    <div class="cms-inspector-toolbar">
        <x-admin.button variant="ghost" size="sm" @click="showStructure = !showStructure" x-bind:aria-expanded="showStructure">Estructura</x-admin.button>
        <x-admin.button variant="ghost" size="sm" @click="undo()" x-bind:disabled="!canUndo()">↶ Deshacer</x-admin.button>
        <x-admin.button variant="ghost" size="sm" @click="redo()" x-bind:disabled="!canRedo()">↷ Rehacer</x-admin.button>
        <x-admin.button variant="secondary" size="sm" @click="openAddModalAt(null)">Añadir composición</x-admin.button>
        <div class="cms-inspector-mobile-switch" role="group" aria-label="Vista del editor">
            <button type="button" @click="inspectorMobilePane = 'edit'" :aria-pressed="inspectorMobilePane === 'edit'">Editar</button>
            <button type="button" @click="inspectorMobilePane = 'preview'; previewSelected()" :aria-pressed="inspectorMobilePane === 'preview'">Vista previa</button>
        </div>
    </div>
    <div class="cms-inspector-grid" :data-mobile-pane="inspectorMobilePane">
        <aside class="cms-inspector-edit" aria-label="Inspector del bloque">
            <nav x-show="showStructure" aria-label="Estructura de bloques" class="cms-inspector-tree">
                <div x-show="blocks.length === 0" class="cms-inspector-empty"><p>Añade una composición para empezar.</p><button type="button" class="cms-inspector-action" @click="openAddModalAt(0)">Añadir la primera pieza</button></div>
                <div data-editor-root-list x-init="mountRootSortable($el)">
                    <template x-for="(block, index) in blocks" :key="block.id || index">
                        <div :data-block-index="index" :data-editor-node="block.id || index" :data-node-unavailable="containsUnavailableBlock(block) ? 'true' : null" class="cms-inspector-tree-root">
                            <div class="cms-inspector-tree-line">
                                <button type="button" data-node-drag @keydown.alt.arrow-up.prevent="moveUp(index)" @keydown.alt.arrow-down.prevent="moveDown(index)" aria-label="Arrastrar composición; Alt y flechas para mover" class="cms-inspector-drag">↕</button>
                                <button type="button" @click="selectNode([index]); inspectorMobilePane = 'edit'" :aria-pressed="selectedNode() === block && !inspectorColumn()" class="cms-inspector-tree-label"><span x-text="typeDef(block.type).name || block.type"></span><small x-show="block.title" x-text="block.title"></small></button>
                                <button type="button" @click="openAppearanceModal(index)" aria-label="Diseñar composición" class="cms-inspector-icon">◈</button>
                            </div>
                            <template x-for="row in inspectorTreeRows().filter(row => row.path[0] === index && row.path.length > 1)" :key="JSON.stringify(row.path)">
                                <button type="button" @click="row.kind === 'column' ? selectColumn(row.path) : selectNode(row.path)" :aria-pressed="row.kind === 'column' ? inspectorColumnId === row.node.id : selectedNode() === row.node && !inspectorColumn()" :style="'padding-inline-start:' + (row.depth * 14 + 24) + 'px'" class="cms-inspector-tree-child"><span x-text="row.name"></span><small x-show="!row.available"> · no disponible</small></button>
                            </template>
                        </div>
                    </template>
                </div>
            </nav>
            <header class="cms-inspector-header"><div><span class="cms-inspector-eyebrow" x-text="!selectedNode() ? 'Inspector' : (inspectorColumn() ? 'Columna' : (isRootSelection() ? 'Composición' : 'Bloque anidado'))"></span><h3 x-text="inspectorLabel()"></h3></div><button type="button" class="cms-inspector-action" @click="previewSelected()" :disabled="!selectedNode() || !editorOptions.previewUrl">Vista pública</button></header>
            <template x-if="selectedNode()">
                <div>
                    <div role="tablist" aria-label="Opciones del bloque" class="cms-inspector-tabs" @keydown.arrow-right.prevent="nextInspectorTab(1)" @keydown.arrow-left.prevent="nextInspectorTab(-1)" @keydown.home.prevent="inspectorTab = 'content'; focusInspectorTab()" @keydown.end.prevent="inspectorTab = 'advanced'; focusInspectorTab()">
                        <button type="button" role="tab" data-inspector-tab="content" :aria-selected="inspectorTab === 'content'" :tabindex="inspectorTab === 'content' ? 0 : -1" @click="inspectorTab = 'content'">Contenido</button>
                        <button type="button" role="tab" data-inspector-tab="design" :aria-selected="inspectorTab === 'design'" :tabindex="inspectorTab === 'design' ? 0 : -1" @click="inspectorTab = 'design'">Diseño</button>
                        <button type="button" role="tab" data-inspector-tab="advanced" :aria-selected="inspectorTab === 'advanced'" :tabindex="inspectorTab === 'advanced' ? 0 : -1" @click="inspectorTab = 'advanced'">Avanzado</button>
                    </div>
                    <div class="cms-inspector-panel" role="tabpanel" :aria-label="{ content: 'Contenido', design: 'Diseño', advanced: 'Avanzado' }[inspectorTab]">
                        <p x-show="!isBlockAvailable(selectedNode())" x-text="unavailableBlockMessage(selectedNode())" class="admin-field-hint" role="status"></p>
                        <template x-if="isBlockAvailable(selectedNode())">
                            <div>
                                <div x-show="inspectorTab !== 'content'" class="cms-inspector-scopes">
                                    <label class="admin-field-label">Dispositivo<select class="admin-control" :value="inspectorDevice" @change="setInspectorDevice($event.target.value)" aria-label="Dispositivo de diseño"><option value="base">Escritorio</option><option value="tablet">Tableta</option><option value="mobile">Móvil</option></select></label>
                                    <label class="admin-field-label">Estado global<select class="admin-control" :value="inspectorState" @change="setInspectorState($event.target.value)" aria-label="Estado global"><option value="base">Normal</option><option value="hover">Al pasar el puntero</option><option value="focus">Con foco</option><option value="active">Al activar</option><option value="selected">Seleccionado</option></select></label>
                                    <p x-show="inspectorState !== 'base'" class="admin-field-hint">Este estado se aplica a todos los dispositivos.</p>
                                </div>
                                <template x-if="inspectorTab === 'content'">
                                    <div>
                                        <template x-if="inspectorColumn()"><div data-schema-editor x-effect="mountInspectorColumn($el)"></div></template>
                                        <template x-if="!inspectorColumn()">
                                            <div>
                                                <template x-for="entry in inspectorEntries()" :key="entry.key">
                                                    <div x-data="{ get inspectedBlock() { return selectedNode() } }">
                                                        <template x-if="!typeDef(inspectedBlock.type).editorView"><div data-schema-editor x-effect="mountSchemaEditor($el, inspectedBlock, 'content')"></div></template>
                                                        @foreach($activeBlockTypes as $definition)
                                                            @if($definition->getEditorView())
                                                                <template x-if="inspectedBlock.type === @js($definition->getType())"><div>@include($definition->getEditorView(), ['blockKey' => 'inspectedBlock', 'isSubBlock' => false])</div></template>
                                                            @endif
                                                        @endforeach
                                                        <template x-if="(inspectedBlock.columns || []).length"><div class="cms-inspector-section"><p class="admin-field-hint">Esta composición organiza columnas. Selecciona una columna para editar sus bloques.</p><template x-for="(column, colIndex) in inspectedBlock.columns" :key="column.id"><button type="button" class="cms-inspector-action" @click="selectColumn([blocks.indexOf(inspectedBlock), 'columns', colIndex])" x-text="'Editar columna ' + (colIndex + 1)"></button></template></div></template>
                                                    </div>
                                                </template>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                                <template x-if="inspectorTab !== 'content'"><div data-inspector-design x-effect="mountInspectorDesign($el)"></div></template>
                                <template x-if="inspectorTab === 'design' && isRootSelection()"><div class="cms-inspector-section"><h4>Columnas de sección</h4><p class="admin-field-hint">Para dividir una fila existente. El contenedor tiene su propia distribución.</p><div class="cms-inspector-actions"><template x-for="layout in ['1', '2', '1-2', '2-1', '3', '4']" :key="layout"><button type="button" class="cms-inspector-action" :aria-pressed="activeLayout(selectedNode()) === layout" :disabled="!canSetColumnsLayout(selectedNode(), layout)" @click="setColumnsLayout(blocks.indexOf(selectedNode()), layout)" x-text="layout"></button></template></div></div></template>
                                <template x-if="inspectorTab === 'advanced' && !inspectorColumn()"><div>@include('components.admin.blocks.inspector.advanced')</div></template>
                            </div>
                        </template>
                        <div class="cms-inspector-actions" x-show="isRootSelection()">
                            <button type="button" class="cms-inspector-action" @click="openAddModalAt(blocks.indexOf(selectedNode()))">Añadir antes</button>
                            <button type="button" class="cms-inspector-action" @click="openAddModalAt(blocks.indexOf(selectedNode()) + 1)">Añadir después</button>
                            <button type="button" class="cms-inspector-action" @click="moveUp(blocks.indexOf(selectedNode()))" :disabled="blocks.indexOf(selectedNode()) <= 0">Subir</button>
                            <button type="button" class="cms-inspector-action" @click="moveDown(blocks.indexOf(selectedNode()))" :disabled="blocks.indexOf(selectedNode()) >= blocks.length - 1">Bajar</button>
                            <button type="button" class="cms-inspector-action" @click="cloneBlock(blocks.indexOf(selectedNode()))" :disabled="containsUnavailableBlock(selectedNode())">Duplicar</button>
                            <button type="button" class="cms-inspector-action" @click="removeBlock(blocks.indexOf(selectedNode()))" :disabled="containsUnavailableBlock(selectedNode())">Eliminar</button>
                        </div>
                    </div>
                </div>
            </template>
            <p x-show="!selectedNode()" class="cms-inspector-empty">Selecciona una pieza en la estructura o en la vista previa.</p>
        </aside>
        <section data-composition-preview data-block-preview class="cms-inspector-preview" aria-label="Vista previa de la composición">
            <div class="cms-inspector-preview-toolbar"><div role="group" aria-label="Tamaño de vista previa"><button type="button" @click="previewViewport = 'desktop'" :aria-pressed="previewViewport === 'desktop'">Escritorio</button><button type="button" @click="previewViewport = 'tablet'" :aria-pressed="previewViewport === 'tablet'">Tableta</button><button type="button" @click="previewViewport = 'mobile'" :aria-pressed="previewViewport === 'mobile'">Móvil</button></div><div role="group" aria-label="Zoom de vista previa"><button type="button" @click="previewZoom = 'fit'" :aria-pressed="previewZoom === 'fit'">Ajustar</button><button type="button" @click="previewZoom = 'actual'" :aria-pressed="previewZoom === 'actual'">100%</button><span class="admin-field-hint" aria-label="Escala actual" x-text="Math.round(previewScale * 100) + '%'"></span></div><button type="button" @click="toggleFullscreen()" class="cms-inspector-action" :aria-pressed="isFullscreen" x-text="isFullscreen ? 'Salir de pantalla completa' : 'Ampliar editor'"></button><button type="button" @click="replayPreview()" class="cms-inspector-action" :disabled="!previewHtml">Repetir animación</button><button type="button" class="cms-inspector-mobile-switch" @click="closePreview()">Cerrar vista</button></div>
            <div class="cms-inspector-preview-status" role="status"><span x-show="previewBusy">Actualizando composición…</span><span x-show="inspectorHasInvalid()">Revisa el valor incompleto. Se conserva la última vista válida.</span><span x-show="previewError" x-text="previewError"></span><span x-show="!previewBusy && !previewError && !inspectorHasInvalid()">Vista de la composición seleccionada · los formularios no se envían.</span></div>
            <div class="cms-inspector-preview-surface" :data-zoom="previewZoom" x-effect="mountPreviewViewport($el)">
                <p x-show="!previewHtml" class="cms-inspector-empty">La vista aparecerá al seleccionar una composición.</p>
                <iframe x-show="previewHtml" :srcdoc="previewHtml" :style="previewFrameStyle()" @load="sendPreviewSelection()" sandbox="allow-scripts allow-same-origin" referrerpolicy="no-referrer" title="Vista pública en lectura; el envío de formularios está desactivado"></iframe>
            </div>
        </section>
    </div>
</section>
