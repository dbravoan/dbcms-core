@props([
    'show' => 'showModal',
    'title' => null,
    'subtitle' => null,
    'icon' => null,
    'maxWidth' => '2xl',
    'closable' => true,
    'onClose' => null,
    'transparent' => false,
    'maximizable' => true,
])

@php
    $maxWidthClass = match ($maxWidth) {
        'sm' => 'max-w-sm',
        'md' => 'max-w-md',
        'lg' => 'max-w-lg',
        'xl' => 'max-w-xl',
        '2xl' => 'max-w-2xl',
        '3xl' => 'max-w-3xl',
        '4xl' => 'max-w-4xl',
        '5xl' => 'max-w-5xl',
        '6xl' => 'max-w-6xl',
        '7xl' => 'max-w-7xl',
        'full' => 'max-w-full',
        default => (str_starts_with((string) $maxWidth, 'max-w-') ? $maxWidth : 'max-w-2xl'),
    };

    $closeExpr = $onClose ?: ($show . ' = false');
    $backdropClass = $transparent
        ? 'bg-transparent backdrop-blur-md'
        : 'bg-black/40 backdrop-blur-md';
@endphp

<div {{ $attributes->merge(['class' => 'fixed inset-0 z-50 overflow-y-auto']) }}
     x-data="{ isMaximized: false }"
     x-show="{{ $show }}"
     x-cloak
     style="display: none;">

    <!-- Fullscreen Backdrop Layer -->
    <div class="fixed inset-0 {{ $backdropClass }} transition-opacity"
         @if($closable) @click="{{ $closeExpr }}" @endif
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"></div>

    <!-- Centering Shell (Guarantees vertical centering without top cutoffs) -->
    <div class="flex min-h-full items-center justify-center p-4 sm:p-6 text-center"
         @if($closable)
             @keydown.escape.window="{{ $closeExpr }}"
         @endif>

        <div class="admin-dialog relative w-full {{ $maxWidthClass }} text-left transform shadow-2xl font-mono rounded-2xl p-5 sm:p-6 transition-all duration-200"
             role="dialog" aria-modal="true" @if($title) aria-label="{{ $title }}" @endif
             :class="isMaximized ? '!w-[96vw] !max-w-none !h-[92vh] !max-h-none flex flex-col' : ''"
             @if($closable)
                 @click.outside="{{ $closeExpr }}"
             @endif
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95 translate-y-2"
             x-transition:enter-end="opacity-100 scale-100 translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 scale-100 translate-y-0"
             x-transition:leave-end="opacity-0 scale-95 translate-y-2">

        @if(isset($header) || $title)
            <div class="admin-dialog-divider flex items-start justify-between gap-3 pb-4 mb-4 border-b shrink-0">
                @if(isset($header))
                    <div class="flex-1 min-w-0">
                        {{ $header }}
                    </div>
                @else
                    <div class="flex items-center gap-2.5 min-w-0">
                        @if($icon)
                            <div class="admin-selection-chip w-8 h-8 justify-center shrink-0" aria-hidden="true">
                                <i class="{{ $icon }}"></i>
                            </div>
                        @endif
                        <div class="truncate">
                            <h3 class="admin-dialog-title text-sm font-bold flex items-center gap-2 truncate">
                                <span class="prompt-prefix text-[color:var(--admin-accent)]">></span> {{ $title }}
                            </h3>
                            @if($subtitle)
                                <p class="admin-text-muted mt-0.5 truncate">{{ $subtitle }}</p>
                            @endif
                        </div>
                    </div>
                @endif

                <div class="flex items-center gap-1.5 shrink-0">
                    @if($maximizable)
                        <button type="button"
                                @click="isMaximized = !isMaximized"
                                class="admin-btn admin-btn--icon"
                                :aria-label="isMaximized ? @js(__('admin.actions.restore')) : @js(__('admin.actions.maximize'))"
                                :title="isMaximized ? @js(__('admin.actions.restore')) : @js(__('admin.actions.maximize'))">
                            <i class="fa-solid" :class="isMaximized ? 'fa-compress' : 'fa-expand'"></i>
                        </button>
                    @endif

                    @if($closable)
                        <button type="button"
                                @click="{{ $closeExpr }}"
                                class="admin-btn admin-btn--icon"
                                aria-label="{{ __('admin.actions.close_esc') }}"
                                title="{{ __('admin.actions.close_esc') }}">
                            <i class="fa-solid fa-xmark text-base"></i>
                        </button>
                    @endif
                </div>
            </div>
        @endif

        <div class="space-y-4" :class="{ 'flex-1 overflow-y-auto pr-1': isMaximized }">
            {{ $slot }}
        </div>

        @if(isset($footer))
            <div class="admin-dialog-divider pt-4 mt-5 border-t flex flex-wrap items-center justify-end gap-3 shrink-0">
                {{ $footer }}
            </div>
        @endif
    </div>
</div>
</div>
