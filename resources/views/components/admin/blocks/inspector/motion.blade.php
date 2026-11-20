{{-- Emergente Animación: secciones y bloques (no columnas); mismo kit modal. --}}
<x-admin.modal
    show="showMotionDialog"
    :title="__('admin.block_manager.animations')"
    icon="fa-solid fa-bolt"
    max-width="2xl"
    z-index="z-[120]"
    on-close="closeMotionDialog()"
    body-class="space-y-4"
    data-motion-dialog
>
    <x-slot:subtitleSlot>
        <span x-text="@js(__('admin.block_manager.editing')) + ' ' + inspectorLabel()"></span>
    </x-slot:subtitleSlot>

    <p x-show="!canEditMotion()" class="admin-field-hint" role="status">{{ __('admin.block_manager.section_motion_hint') }}</p>
    <template x-if="showMotionDialog && canEditMotion()">
        <div data-inspector-motion x-effect="mountInspectorMotion($el)"></div>
    </template>
</x-admin.modal>
