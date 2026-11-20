            <!-- Maintenance Mode Indicator -->
            @if(app(\DbCms\Core\Maintenance\Services\MaintenanceService::class)->isActive())
                <a href="{{ route('admin.settings.features.edit', ['feature' => 'maintenance']) }}" class="px-2 sm:px-2.5 py-1 rounded-full border border-amber-500/40 bg-amber-500/10 text-amber-500 font-mono text-[10px] font-bold inline-flex items-center gap-1.5 animate-pulse shrink-0 admin-badge-maintenance" title="{{ __('admin.front_bar.tooltip_maintenance') }}">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <span class="hidden sm:inline">{{ __('admin.front_bar.maintenance_active') }}</span>
                </a>
            @endif
