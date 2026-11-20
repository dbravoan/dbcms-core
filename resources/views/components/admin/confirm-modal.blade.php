<div x-data="{
        showModal: false,
        actionUrl: '',
        httpMethod: 'POST',
        modalTitle: @js(__('admin.modal.confirm_title')),
        modalMessage: @js(__('admin.modal.delete_message')),
        confirmButtonText: @js(__('admin.actions.confirm')),
        confirmButtonClass: 'admin-btn--primary',
        modalIcon: 'fa-solid fa-circle-question',
        open(detail) {
            this.actionUrl = detail.action || '';
            this.httpMethod = (detail.method || 'POST').toUpperCase();
            this.modalTitle = detail.title || @js(__('admin.modal.confirm_title'));
            this.modalMessage = detail.message || @js(__('admin.modal.delete_message'));
            this.confirmButtonText = detail.buttonText || @js(__('admin.actions.confirm'));
            this.confirmButtonClass = detail.buttonClass || 'admin-btn--primary';
            this.modalIcon = detail.icon || 'fa-solid fa-circle-question';
            this.showModal = true;
        }
     }"
     @confirm-action.window="open($event.detail)">
    <x-admin.modal show="showModal" max-width="md" z-index="z-[220]" :maximizable="false">
        <x-slot:header>
            <h3 class="admin-dialog-title flex items-center gap-2 text-sm font-bold">
                <i :class="modalIcon" aria-hidden="true"></i>
                <span x-text="modalTitle"></span>
            </h3>
        </x-slot:header>

        <p class="admin-dialog-message" x-text="modalMessage"></p>

        <x-slot:footer>
            <form method="POST" :action="actionUrl" class="flex flex-wrap justify-end gap-3">
                @csrf
                <template x-if="httpMethod !== 'POST' && httpMethod !== 'GET'">
                    <input type="hidden" name="_method" :value="httpMethod">
                </template>
                <x-admin.button @click="showModal = false" data-admin-dialog-initial-focus>
                    {{ __('admin.actions.cancel') }}
                </x-admin.button>
                <button type="submit" :class="confirmButtonClass" class="admin-btn" x-text="confirmButtonText"></button>
            </form>
        </x-slot:footer>
    </x-admin.modal>
</div>
