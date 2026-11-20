{{-- Visor bajo demanda sobre el kit modal; cierre × canónico (sin texto «Cerrar»). --}}
<x-admin.modal
    show="showPreview"
    :title="__('admin.block_manager.preview_open')"
    icon="fa-solid fa-eye"
    max-width="7xl"
    z-index="z-[120]"
    on-close="closePreview()"
    panel-class="!p-0 sm:!p-0 overflow-hidden"
    body-class="!space-y-0"
    data-preview-dialog
>
    <x-slot:toolbar>
        <div role="group" aria-label="{{ __('admin.block_manager.preview_size') }}" class="cms-inspector-toolbar-group">
            <x-admin.button type="button" variant="ghost" size="sm" @click="previewViewport = 'desktop'" alpine-active="previewViewport === 'desktop'" x-bind:aria-pressed="previewViewport === 'desktop'">{{ __('admin.block_manager.viewport_desktop') }}</x-admin.button>
            <x-admin.button type="button" variant="ghost" size="sm" @click="previewViewport = 'tablet'" alpine-active="previewViewport === 'tablet'" x-bind:aria-pressed="previewViewport === 'tablet'">{{ __('admin.block_manager.viewport_tablet') }}</x-admin.button>
            <x-admin.button type="button" variant="ghost" size="sm" @click="previewViewport = 'mobile'" alpine-active="previewViewport === 'mobile'" x-bind:aria-pressed="previewViewport === 'mobile'">{{ __('admin.block_manager.viewport_mobile') }}</x-admin.button>
        </div>
        <div role="group" aria-label="{{ __('admin.block_manager.preview_zoom') }}" class="cms-inspector-toolbar-group">
            <x-admin.button type="button" variant="ghost" size="sm" @click="previewZoom = 'fit'" alpine-active="previewZoom === 'fit'" x-bind:aria-pressed="previewZoom === 'fit'">{{ __('admin.block_manager.zoom_fit') }}</x-admin.button>
            <x-admin.button type="button" variant="ghost" size="sm" @click="previewZoom = 'actual'" alpine-active="previewZoom === 'actual'" x-bind:aria-pressed="previewZoom === 'actual'">100%</x-admin.button>
            <span class="admin-field-hint" aria-label="{{ __('admin.block_manager.preview_scale') }}" x-text="Math.round(previewScale * 100) + '%'"></span>
        </div>
        <x-admin.button type="button" variant="ghost" size="sm" @click="toggleFullscreen()" alpine-active="isFullscreen" x-bind:aria-pressed="isFullscreen"><span x-text="isFullscreen ? @js(__('admin.block_manager.exit_fullscreen')) : @js(__('admin.block_manager.expand_editor'))"></span></x-admin.button>
        <x-admin.button type="button" variant="ghost" size="sm" @click="replayPreview()" x-bind:disabled="!previewHtml">{{ __('admin.block_manager.replay_animation') }}</x-admin.button>
    </x-slot:toolbar>

    <section data-composition-preview data-block-preview class="cms-inspector-preview cms-inspector-preview--dialog" :aria-label="previewScope === 'all' ? @js(__('admin.block_manager.preview_label_all')) : @js(__('admin.block_manager.preview_label'))">
        <div class="cms-inspector-preview-status" role="status"><span x-show="previewBusy" x-text="previewScope === 'all' ? @js(__('admin.block_manager.preview_busy_all')) : @js(__('admin.block_manager.preview_busy'))"></span><span x-show="inspectorHasInvalid()">{{ __('admin.block_manager.preview_invalid') }}</span><span x-show="previewError" x-text="previewError"></span><span x-show="!previewBusy && !previewError && !inspectorHasInvalid()" x-text="previewScope === 'all' ? @js(__('admin.block_manager.preview_ready_all')) : @js(__('admin.block_manager.preview_ready'))"></span></div>
        <div class="cms-inspector-preview-surface" :data-zoom="previewZoom" x-effect="mountPreviewViewport($el)">
            <p x-show="!previewHtml" class="cms-inspector-empty">{{ __('admin.block_manager.preview_empty') }}</p>
            <iframe data-cms-preview-frame x-show="previewHtml" :srcdoc="previewHtml" :style="previewFrameStyle()" @load="sendPreviewSelection()" sandbox="allow-scripts allow-same-origin" referrerpolicy="no-referrer" title="{{ __('admin.block_manager.preview_frame_title') }}"></iframe>
        </div>
    </section>
</x-admin.modal>
