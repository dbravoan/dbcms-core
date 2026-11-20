            <a href="{{ route('admin.forms.index') }}" class="admin-card p-5 hover:border-[color:var(--admin-accent)]/50 hover:bg-[color:var(--admin-surface)] transition-all duration-200 group block">
                <div class="flex items-center justify-between mb-3">
                    <span class="w-10 h-10 rounded-lg bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] group-hover:border-[color:var(--admin-accent)]/40 transition-colors flex items-center justify-center text-[color:var(--admin-accent)] text-base"><i class="fa-solid fa-inbox"></i></span>
                    <span class="text-[color:var(--admin-muted)] group-hover:text-[color:var(--admin-accent)] text-xs font-mono transition-colors">&rarr;</span>
                </div>
                <h3 class="font-bold text-[color:var(--admin-text)] text-sm group-hover:text-[color:var(--admin-accent)] transition-colors">{{ __('admin.dashboard.manage_forms') }}</h3>
                <p class="text-xs text-[color:var(--admin-muted)] mt-1 leading-relaxed">{{ __('admin.dashboard.manage_forms_desc') }}</p>
            </a>
