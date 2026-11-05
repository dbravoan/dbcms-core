<div x-data="{
        showModal: false,
        actionUrl: '',
        httpMethod: 'POST',
        modalTitle: @js(__('admin.modal.confirm_title')),
        modalMessage: @js(__('admin.modal.delete_message')),
        confirmButtonText: @js(__('admin.actions.confirm')),
        confirmButtonClass: 'btn-neon',
        modalIcon: 'fa-solid fa-circle-question',
        open(detail) {
            this.actionUrl = detail.action || '';
            this.httpMethod = (detail.method || 'POST').toUpperCase();
            this.modalTitle = detail.title || @js(__('admin.modal.confirm_title'));
            this.modalMessage = detail.message || @js(__('admin.modal.delete_message'));
            this.confirmButtonText = detail.buttonText || @js(__('admin.actions.confirm'));
            this.confirmButtonClass = detail.buttonClass || 'btn-neon';
            this.modalIcon = detail.icon || 'fa-solid fa-circle-question';
            this.showModal = true;
        }
     }"
     @confirm-action.window="open($event.detail)"
     @keydown.escape.window="showModal = false"
     x-show="showModal"
     x-cloak
     class="fixed inset-0 z-50 overflow-y-auto"
     style="display: none;">

    <div class="fixed inset-0 bg-[color:var(--admin-surface)]/60 backdrop-blur-md transition-opacity"
         @click="showModal = false"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"></div>

    <div class="flex min-h-full items-center justify-center p-4 sm:p-6 text-center"
         @keydown.escape.window="showModal = false">

        <div class="relative bg-[color:var(--admin-surface)] border border-[color:var(--admin-accent)]/40 shadow-[0_0_40px_rgba(0,255,153,0.15)] font-mono text-left max-w-md w-full rounded-2xl p-6 space-y-4 transform transition-all"
             @click.outside="showModal = false"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95 translate-y-2"
             x-transition:enter-end="opacity-100 scale-100 translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 scale-100 translate-y-0"
             x-transition:leave-end="opacity-0 scale-95 translate-y-2">
        
        <div class="flex items-center justify-between pb-3 border-b border-[color:var(--admin-border)]">
            <h3 class="text-sm font-bold text-[color:var(--admin-text)] flex items-center gap-2.5">
                <i :class="modalIcon" class="text-[color:var(--admin-accent)]"></i>
                <span x-text="modalTitle"></span>
            </h3>
            <button type="button" @click="showModal = false" class="text-[color:var(--admin-muted)] hover:text-[color:var(--admin-text)] transition-colors">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <p class="text-xs text-[color:var(--admin-text)] leading-relaxed" x-text="modalMessage"></p>

        <form method="POST" :action="actionUrl" class="flex justify-end gap-3 pt-3 border-t border-[color:var(--admin-border)]">
            @csrf
            <template x-if="httpMethod !== 'POST' && httpMethod !== 'GET'">
                <input type="hidden" name="_method" :value="httpMethod">
            </template>

            <button type="button" @click="showModal = false" class="px-4 py-2 bg-[color:var(--admin-surface-raised)]/80 hover:bg-[color:var(--admin-surface-raised)] text-[color:var(--admin-text)] rounded-lg text-xs transition-colors font-medium">
                {{ __('admin.actions.cancel') }}
            </button>
            <button type="submit" :class="confirmButtonClass" class="px-4 py-2 text-xs font-bold rounded-lg transition-all" x-text="confirmButtonText">
            </button>
        </form>
    </div>
</div>
</div>
