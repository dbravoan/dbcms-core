{{-- Cuerpo de una tarjeta de sección: rejilla de columnas o formulario propio. --}}
<template x-if="hasColumnContent(block)">
    <div class="space-y-4">
        <div class="admin-block-heading flex flex-wrap items-center justify-between gap-2 pb-2 border-b border-[color:var(--admin-border)]">
            <div class="text-xs text-[color:var(--admin-muted)] flex items-center gap-2">
                <span class="text-[color:var(--admin-accent)] font-bold">▦ {{ __('admin.block_manager.columns_layout') }}</span>
                <span class="px-2 py-0.5 rounded bg-[color:var(--admin-surface-raised)] text-[color:var(--admin-text)] font-bold" x-text="block.columns_layout === '2' ? '2 Columnas (50% / 50%)' : (block.columns_layout === '1-2' ? '2 Columnas (33% / 66%)' : (block.columns_layout === '2-1' ? '2 Columnas (66% / 33%)' : (block.columns_layout === '3' ? '3 Columnas (33% / 33% / 33%)' : (block.columns_layout === '1' ? '1 Columna' : '4 Columnas'))))"></span>
            </div>
        </div>

        <div class="admin-block-columns grid gap-6"
             :class="block.columns_layout === '2' ? 'grid-cols-1 md:grid-cols-2' : (block.columns_layout === '3' ? 'grid-cols-1 md:grid-cols-3' : (block.columns_layout === '4' ? 'grid-cols-1 md:grid-cols-2 lg:grid-cols-4' : (block.columns_layout === '1' ? 'grid-cols-1' : 'grid-cols-1 md:grid-cols-3')))">
            <template x-for="(col, colIdx) in blockColumns(block)" :key="col.id || colIdx">
                <div class="admin-block-column bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] rounded-xl p-4 flex flex-col items-start justify-start"
                     :class="block.columns_layout === '1-2' && colIdx === 1 ? 'md:col-span-2' : (block.columns_layout === '2-1' && colIdx === 0 ? 'md:col-span-2' : 'col-span-1')">

                    <div class="admin-block-heading w-full flex flex-wrap items-center justify-between gap-2 pb-3 mb-3 border-b border-[color:var(--admin-border)]/80">
                        <span class="text-xs font-bold text-[color:var(--admin-text)]" x-text="'Columna ' + (colIdx + 1) + (block.columns_layout === '1-2' ? (colIdx === 0 ? ' (33%)' : ' (66%)') : (block.columns_layout === '2-1' ? (colIdx === 0 ? ' (66%)' : ' (33%)') : ''))"></span>
                        <x-admin.button type="button" variant="icon" size="sm" @click="openColumnAppearanceModal(index, colIdx)" icon="fa-solid fa-palette" title="{{ __('admin.block_manager.column_appearance_tooltip') }}" aria-label="{{ __('admin.block_manager.column_appearance_tooltip') }}"></x-admin.button>
                    </div>

                    {{-- Sortable needs each [data-editor-node] as a direct child; empty CTA stays outside the list. --}}
                    <div class="w-full space-y-1">
                        <div class="space-y-1 min-h-[100px]" data-editor-slot-list data-column-list x-init="mountColumnSortable($el, index, colIdx)">
                            <template x-for="(subBlock, subIdx) in (col.blocks || [])" :key="subBlock.id || subIdx">
                                <div class="admin-block-nested bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] rounded-lg p-3 space-y-3" data-editor-node :data-node-unavailable="containsUnavailableBlock(subBlock) ? 'true' : null" :data-selected="isNodeInSelection([index, 'columns', colIdx, 'blocks', subIdx]) ? 'true' : null">
                                    <div x-show="subIdx === 0" class="admin-block-insert" :data-insert-zone="'c-' + index + '-' + colIdx + '-0'">
                                        <div class="admin-block-insert-line" aria-hidden="true"></div>
                                        <button type="button" @click="openAddModalAt(null, { blockIndex: index, colIndex: colIdx, insertAt: 0 })" class="admin-block-insert-btn">
                                            <i class="fa-solid fa-plus text-[10px]" aria-hidden="true"></i>
                                            <span>{{ __('admin.block_manager.add_block') }}</span>
                                        </button>
                                    </div>

                                    <div class="admin-block-heading flex flex-wrap items-center justify-between gap-2 pb-2 border-b border-[color:var(--admin-border)]">
                                        <div class="flex items-center gap-2">
                                            <button type="button" data-node-drag class="admin-block-drag p-0.5 rounded text-[color:var(--admin-muted)] hover:text-[color:var(--admin-text)] cursor-grab disabled:opacity-20" :disabled="containsUnavailableBlock(subBlock)" @keydown.alt.arrow-up.prevent="moveColumnBlockUp(index, colIdx, subIdx)" @keydown.alt.arrow-down.prevent="moveColumnBlockDown(index, colIdx, subIdx)" aria-label="{{ __('admin.block_manager.drag_block') }}" title="{{ __('admin.block_manager.drag_block') }}"><i class="fa-solid fa-grip-vertical text-[10px]" aria-hidden="true"></i></button>
                                            <i x-show="isFontAwesomeIcon(typeDef(subBlock.type).icon)" :class="typeDef(subBlock.type).icon" class="text-xs text-[color:var(--admin-accent)]" x-cloak></i>
                                            <span x-show="!isFontAwesomeIcon(typeDef(subBlock.type).icon)" class="text-xs text-[color:var(--admin-accent)] font-bold" x-text="typeGlyph(subBlock.type)" x-cloak></span>
                                            <span class="text-xs font-bold text-[color:var(--admin-text)] uppercase" x-text="availableTypes.find(t => t.type === subBlock.type)?.name || subBlock.type"></span>
                                        </div>
                                        <div class="flex items-center gap-1" x-show="isBlockAvailable(subBlock)" x-cloak>
                                            <x-admin.button type="button" variant="icon" size="sm" @click="openAppearanceModal(index, { colIndex: colIdx, subIndex: subIdx })" icon="fa-solid fa-palette" title="{{ __('admin.block_manager.appearance_tooltip') }}" aria-label="{{ __('admin.block_manager.appearance_tooltip') }}"></x-admin.button>
                                            <x-admin.button type="button" variant="icon" size="sm" @click="openMotionDialog(index, { colIndex: colIdx, subIndex: subIdx })" icon="fa-solid fa-bolt" title="{{ __('admin.block_manager.motion_tooltip') }}" aria-label="{{ __('admin.block_manager.motion_tooltip') }}"></x-admin.button>
                                            <x-admin.button type="button" variant="icon" size="sm" @click="moveColumnBlockUp(index, colIdx, subIdx)" x-bind:disabled="subIdx === 0" title="{{ __('admin.block_manager.move_up_short') }}" aria-label="{{ __('admin.block_manager.move_up_short') }}">↑</x-admin.button>
                                            <x-admin.button type="button" variant="icon" size="sm" @click="moveColumnBlockDown(index, colIdx, subIdx)" x-bind:disabled="subIdx === col.blocks.length - 1" title="{{ __('admin.block_manager.move_down_short') }}" aria-label="{{ __('admin.block_manager.move_down_short') }}">↓</x-admin.button>
                                            <x-admin.button type="button" variant="icon" size="sm" @click="removeColumnBlock(index, colIdx, subIdx)" class="text-red-400 hover:text-red-300" title="{{ __('admin.block_manager.delete') }}" aria-label="{{ __('admin.block_manager.delete') }}">&times;</x-admin.button>
                                        </div>
                                    </div>

                                    <div class="pt-1 space-y-3">
                                        <div x-show="isBlockAvailable(subBlock)">
                                        @include('components.admin.blocks._block_form_body', [
                                            'blockKey' => 'subBlock',
                                            'dynamicPayloads' => $dynamicPayloads ?? [],
                                            'isSubBlock' => true,
                                        ])
                                        </div>
                                    </div>

                                    <div class="admin-block-insert" :data-insert-zone="'c-' + index + '-' + colIdx + '-' + (subIdx + 1)">
                                        <div class="admin-block-insert-line" aria-hidden="true"></div>
                                        <button type="button" @click="openAddModalAt(null, { blockIndex: index, colIndex: colIdx, insertAt: subIdx + 1 })" class="admin-block-insert-btn">
                                            <i class="fa-solid fa-plus text-[10px]" aria-hidden="true"></i>
                                            <span x-text="subIdx === (col.blocks.length - 1) ? @js(__('admin.block_manager.add_block_here')) : @js(__('admin.block_manager.add_block'))"></span>
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <template x-if="!col.blocks || col.blocks.length === 0">
                            <button type="button"
                                    @click="openAddModalAt(null, { blockIndex: index, colIndex: colIdx, insertAt: 0 })"
                                    class="w-full text-center py-10 border border-dashed border-[color:var(--admin-border)] hover:border-[color:var(--admin-accent)]/60 hover:bg-[color:var(--admin-accent)]/[0.03] rounded-lg text-[color:var(--admin-muted)] hover:text-[color:var(--admin-accent)] text-xs transition-colors">
                                <i class="fa-solid fa-plus mr-1.5"></i>{{ __('admin.block_manager.add_block_empty') }}
                            </button>
                        </template>
                    </div>
                </div>
            </template>
        </div>
    </div>
</template>

<template x-if="!hasColumnContent(block)">
    <div class="space-y-6">
        @include('components.admin.blocks._block_form_body', [
            'blockKey' => 'block',
            'dynamicPayloads' => $dynamicPayloads ?? [],
            'isSubBlock' => false,
        ])
    </div>
</template>
