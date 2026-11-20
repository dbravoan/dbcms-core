{{-- Emergente Diseño: una sola cáscara <x-admin.modal>; Avanzado va agrupado dentro. --}}
<x-admin.modal
    show="showDesignDialog"
    :title="__('admin.block_manager.tab_design')"
    icon="fa-solid fa-palette"
    max-width="3xl"
    z-index="z-[120]"
    on-close="closeDesignDialog()"
    body-class="space-y-4"
    data-design-dialog
>
    <x-slot:subtitleSlot>
        <span x-text="@js(__('admin.block_manager.editing')) + ' ' + inspectorLabel()"></span>
        <span x-show="inspectorState !== 'base'" class="block" x-cloak>{{ __('admin.block_manager.state_all_devices') }}</span>
    </x-slot:subtitleSlot>

    <p x-show="!inspectorColumn() && !isBlockAvailable(selectedNode())" x-text="unavailableBlockMessage(selectedNode())" class="admin-field-hint" role="status"></p>
    <template x-if="showDesignDialog && (inspectorColumn() || isBlockAvailable(selectedNode()))">
        <div class="cms-dialog-inspector space-y-4">
            <div data-inspector-design x-effect="mountInspectorDesign($el)"></div>
            <template x-if="!inspectorColumn()">
                <div>@include('components.admin.blocks.inspector.advanced')</div>
            </template>
        </div>
    </template>
</x-admin.modal>
