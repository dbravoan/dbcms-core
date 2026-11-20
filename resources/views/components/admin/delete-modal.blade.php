<div x-data="{
        showDeleteModal: false,
        deleteAction: '',
        deleteTitle: @js(__('admin.modal.delete_title')),
        deleteMessage: @js(__('admin.modal.delete_message')),
        open(action, title = null, message = null) {
            this.deleteAction = action;
            this.deleteTitle = title || @js(__('admin.modal.delete_title'));
            this.deleteMessage = message || @js(__('admin.modal.delete_message'));
            this.showDeleteModal = true;
        }
     }"
     @confirm-delete.window="open($event.detail.action, $event.detail.title, $event.detail.message)">
    <x-admin.modal show="showDeleteModal" max-width="md" z-index="z-[220]" :maximizable="false">
        <x-slot:header>
            <h3 class="admin-dialog-title flex items-center gap-2 text-sm font-bold">
                <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                <span x-text="deleteTitle"></span>
            </h3>
        </x-slot:header>

        <p class="admin-dialog-message" x-text="deleteMessage"></p>

        <x-slot:footer>
            <form method="POST" :action="deleteAction" class="flex flex-wrap justify-end gap-3">
                @csrf
                @method('DELETE')
                <x-admin.button @click="showDeleteModal = false" data-admin-dialog-initial-focus>
                    {{ __('admin.actions.cancel') }}
                </x-admin.button>
                <x-admin.button type="submit" variant="danger">
                    {{ __('admin.actions.delete') }}
                </x-admin.button>
            </form>
        </x-slot:footer>
    </x-admin.modal>
</div>
