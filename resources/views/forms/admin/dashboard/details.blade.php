        <x-admin.form-card class="sm:p-7 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between pb-4 border-b border-[color:var(--admin-border)]">
                    <div class="flex items-center gap-2.5">
                        <i class="fa-solid fa-inbox text-[color:var(--admin-accent)] text-base"></i>
                        <h3 class="font-mono text-sm font-bold text-[color:var(--admin-text)] flex items-center gap-2">
                            <span class="text-[color:var(--admin-accent)]">&gt;</span> {{ __('admin.dashboard.recent_leads') }}
                        </h3>
                    </div>
                    <a href="{{ route('admin.forms.submissions') }}" class="text-xs font-mono text-[color:var(--admin-accent)] hover:underline flex items-center gap-1">
                        <span>{{ __('admin.dashboard.view_all') }}</span>
                        <span>&rarr;</span>
                    </a>
                </div>

                @if($recentSubmissions->isEmpty())
                    <div class="text-center py-12 text-xs font-mono text-[color:var(--admin-muted)]">
                        <div class="text-2xl mb-2 opacity-50"><i class="fa-solid fa-inbox"></i></div>
                        &gt; {{ __('admin.dashboard.no_submissions') }}
                    </div>
                @else
                    <div class="divide-y divide-[color:var(--admin-border)]/80 font-mono text-xs mt-3">
                        @foreach($recentSubmissions as $sub)
                            <div class="py-3.5 flex items-center justify-between hover:bg-[color:var(--admin-surface-raised)]/30 px-2 rounded-lg transition-colors">
                                <div class="truncate max-w-[70%]">
                                    <div class="font-bold text-[color:var(--admin-text)] flex items-center gap-2">
                                        <span class="w-1.5 h-1.5 rounded-full bg-[color:var(--admin-accent)]"></span>
                                        <span class="truncate">{{ $sub->form->name ?? __('admin.dashboard.contact_form') }}</span>
                                    </div>
                                    <div class="text-[color:var(--admin-muted)] text-[11px] mt-0.5 pl-3.5 truncate">
                                        {{ $sub->payload['email'] ?? $sub->payload['nombre'] ?? __('admin.dashboard.anonymous_contact') }}
                                    </div>
                                </div>
                                <span class="text-[10px] text-[color:var(--admin-muted)] shrink-0 bg-[color:var(--admin-surface)] px-2 py-1 rounded border border-[color:var(--admin-border)]">
                                    {{ $sub->created_at->diffForHumans() }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="pt-4 mt-4 border-t border-[color:var(--admin-border)]/80 flex items-center justify-between text-[11px] font-mono text-[color:var(--admin-muted)]">
                <span>{{ __('admin.dashboard.total_received') }} <strong class="text-[color:var(--admin-accent)]">{{ $stats['total_submissions'] }}</strong></span>
                <a href="{{ route('admin.forms.submissions') }}" class="hover:text-[color:var(--admin-accent)] transition-colors">{{ __('admin.dashboard.full_inbox') }} &nearr;</a>
            </div>
        </x-admin.form-card>
