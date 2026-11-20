@props(['picker' => false])

@php
    $mediaOptions = [
        'picker' => $picker,
        'filesUrl' => route('admin.media.files'),
        'uploadUrl' => route('admin.media.upload'),
        'importUrl' => route('admin.media.import'),
        'updateUrl' => route('admin.media.update', ['id' => '__ID__']),
        'deleteUrl' => route('admin.media.destroy', ['filename' => '__FILE__']),
        'csrfToken' => csrf_token(),
        'perPage' => 15,
        'perPageOptions' => config('cms.options.per_page', [10, 15, 25, 50, 100]),
        'messages' => __('admin.filemanager'),
    ];
@endphp

<div x-data="mediaManager(@js($mediaOptions))" data-media-manager="{{ $picker ? 'picker' : 'library' }}"
     @cms-assistant-ready.window="aiAvailable = true">
    @if($picker)
        <x-admin.modal show="open" :title="__('admin.filemanager.title')" :subtitle="__('admin.filemanager.subtitle')"
                       icon="fa-solid fa-photo-film" maxWidth="6xl" zIndex="z-[200]" onClose="if (!deleteOpen) close()">
            <x-admin.media-browser />
        </x-admin.modal>
    @else
        <x-admin.form-card>
            <x-admin.media-browser />
        </x-admin.form-card>
    @endif

    <x-admin.modal show="deleteOpen" :title="__('admin.filemanager.delete_title')" icon="fa-solid fa-trash"
                   maxWidth="lg" zIndex="z-[220]" :maximizable="false" onClose="cancelDelete()">
        <p>{{ __('admin.filemanager.delete_warning') }}</p>
        <p class="break-words font-bold" x-text="pendingDelete?.title || pendingDelete?.filename"></p>
        <x-slot:footer>
            <x-admin.button @click="cancelDelete()" x-bind:disabled="deleting">{{ __('admin.actions.cancel') }}</x-admin.button>
            <x-admin.button variant="danger" icon="fa-solid fa-trash" @click="deleteFile()" x-bind:disabled="deleting">
                <span x-text="deleting ? @js(__('admin.filemanager.deleting')) : @js(__('admin.actions.delete'))"></span>
            </x-admin.button>
        </x-slot:footer>
    </x-admin.modal>
</div>
