@props([
    'title' => '',
    'description' => '',
    'backRoute' => null,
    'backLabel' => null,
    'liveUrl' => null,
    'liveLabel' => null,
])

@php
    $backLabel = $backLabel ?? __('admin.actions.back_to_list');
    $liveLabel = $liveLabel ?? __('admin.actions.view').' &nearr;';
@endphp

<header {{ $attributes->class(['admin-page-header']) }}>
    <div>
        <h1 class="admin-page-title">
            <span class="text-[color:var(--admin-accent)]">&gt;</span>
            <span>{{ $title }}</span>
        </h1>
        @if(!empty($description))
            <p class="admin-page-description">{{ $description }}</p>
        @endif
    </div>

    <div class="admin-page-actions">
        @stack('entity_form_header')
        @isset($actions){{ $actions }}@endisset

        @if(!empty($liveUrl))
            <a href="{{ $liveUrl }}" target="_blank" rel="noopener noreferrer" class="text-[color:var(--admin-accent)] hover:underline font-bold flex items-center gap-1">
                <span>{!! $liveLabel !!}</span>
            </a>
        @endif

        @if(!empty($backRoute))
            <a href="{{ $backRoute }}" class="text-[color:var(--admin-muted)] hover:text-[color:var(--admin-text)] flex items-center gap-1 transition-colors">
                <span>&larr;</span>
                <span>{{ $backLabel }}</span>
            </a>
        @endif
    </div>
</header>
