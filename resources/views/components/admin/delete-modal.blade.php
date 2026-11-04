<div x-data="{
        showDeleteModal: false,
        deleteAction: '',
        deleteTitle: @js(__('admin.modal.delete_title')),
        deleteMessage: @js(__('admin.modal.delete_message')),
        open(action, title = null, message = null) {
            this.deleteAction = action;
            if (title) this.deleteTitle = title;
            if (message) this.deleteMessage = message;
            this.showDeleteModal = true;
        }
     }"
     @confirm-delete.window="open($event.detail.action, $event.detail.title, $event.detail.message)"
     @keydown.escape.window="showDeleteModal = false"
     x-show="showDeleteModal"
     x-cloak
     class="fixed inset-0 z-50 overflow-y-auto"
     style="display: none;">

    <div class="fixed inset-0 bg-[color:var(--admin-surface)]/60 backdrop-blur-md transition-opacity"
         @click="showDeleteModal = false"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"></div>

    <div class="flex min-h-full items-center justify-center p-4 sm:p-6 text-center"
         @keydown.escape.window="showDeleteModal = false">

        <div class="relative bg-[color:var(--admin-surface)] border-2 border-red-500/60 shadow-[0_0_40px_rgba(239,68,68,0.3)] font-mono text-left max-w-md w-full rounded-2xl p-6 space-y-4 transform transition-all"
             @click.outside="showDeleteModal = false"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95 translate-y-2"
             x-transition:enter-end="opacity-100 scale-100 translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 scale-100 translate-y-0"
             x-transition:leave-end="opacity-0 scale-95 translate-y-2">
        
        <div class="flex items-center justify-between pb-3 border-b border-[color:var(--admin-border)]">
            <h3 class="text-sm font-bold text-rose-600 flex items-center gap-2">
                <i class="fa-solid fa-triangle-exclamation"></i> <span x-text="deleteTitle"></span>
            </h3>
            <button @click="showDeleteModal = false" class="text-[color:var(--admin-muted)] hover:text-[color:var(--admin-text)]">&times;</button>
        </div>

        <p class="text-xs text-[color:var(--admin-text)] leading-relaxed" x-text="deleteMessage"></p>

        <form method="POST" :action="deleteAction" class="flex justify-end gap-3 pt-3 border-t border-[color:var(--admin-border)]">
            @csrf
            @method('DELETE')
            <button type="button" @click="showDeleteModal = false" class="px-4 py-2 bg-[color:var(--admin-surface)] hover:bg-[color:var(--admin-surface-raised)] border border-[color:var(--admin-border)] text-[color:var(--admin-text)] rounded-lg text-xs font-semibold transition-colors">
                {{ __('admin.actions.cancel') }}
            </button>
            <button type="submit" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-[color:var(--admin-text)] font-bold rounded-lg text-xs shadow-md shadow-red-600/20 transition-all">
                {{ __('admin.actions.delete') }}
            </button>
        </form>
    </div>
    </div>
</div>
