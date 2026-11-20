@props(['title' => null, 'subtitle' => null, 'icon' => null])

<section {{ $attributes->class(['admin-card', 'space-y-5']) }}>
    @if($title)
        <header class="admin-card-header">
            <h2 class="admin-card-title">
                @if($icon)<i class="{{ $icon }}" aria-hidden="true"></i>@endif
                {{ $title }}
            </h2>
            @if($subtitle)<p class="admin-field-hint">{{ $subtitle }}</p>@endif
        </header>
    @endif
    {{ $slot }}
</section>
