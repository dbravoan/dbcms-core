            <a href="{{ route('admin.forms.submissions') }}" class="admin-card p-5 flex-1 min-w-[170px] sm:min-w-[200px] hover:border-[color:var(--admin-accent)]/40 shadow-sm transition-all duration-200 group flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between text-[color:var(--admin-muted)] mb-2">
                        <span class="text-[11px] font-mono uppercase font-semibold group-hover:text-[color:var(--admin-text)]">{{ __('admin.dashboard.leads') }}</span>
                        <span class="p-1.5 rounded bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] flex items-center justify-center text-[color:var(--admin-accent)]"><i class="fa-solid fa-inbox text-xs"></i></span>
                    </div>
                    <div class="text-2xl sm:text-3xl font-black text-[color:var(--admin-accent)] font-mono">{{ $stats['total_submissions'] }}</div>
                </div>
                <div class="text-[10px] text-[color:var(--admin-accent)] font-mono mt-3 pt-2.5 border-t border-[color:var(--admin-border)]/80 flex items-center justify-between">
                    <span>{{ __('admin.dashboard.leads_hint') }}</span>
                    <span class="text-[color:var(--admin-muted)] group-hover:text-[color:var(--admin-accent)]">&rarr;</span>
                </div>
            </a>
