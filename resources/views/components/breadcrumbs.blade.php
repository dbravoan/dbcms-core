@props([
    'trail' => []
])

@php
    $activeTheme = (string) config('cms.theme', 'db-cyber');
    $activeClass = match($activeTheme) {
        'db-cyber' => 'text-[#00FF99]',
        'db-editorial' => 'text-amber-800 dark:text-amber-400 font-serif',
        'db-enterprise' => 'text-indigo-600 dark:text-indigo-400',
        'db-minimal' => 'text-black dark:text-white font-medium',
        'db-sunset' => 'text-rose-400 font-medium',
        'db-brutalist' => 'text-black dark:text-white font-bold',
        default => 'text-current opacity-90',
    };
@endphp

@if(!empty($trail))
    <!-- Visual HTML Breadcrumb Trail -->
    <nav aria-label="Breadcrumb" class="flex items-center text-xs font-mono opacity-80 space-x-2 py-3 overflow-x-auto select-none">
        @foreach($trail as $index => $crumb)
            @if($loop->last)
                <span class="{{ $activeClass }} font-bold truncate max-w-xs sm:max-w-md" aria-current="page">
                    {{ $crumb['title'] }}
                </span>
            @else
                <a href="{{ $crumb['url'] }}" class="opacity-70 hover:opacity-100 transition-opacity truncate">
                    {{ $crumb['title'] }}
                </a>
                <span class="opacity-40">&gt;</span>
            @endif
        @endforeach
    </nav>

    <!-- Schema.org BreadcrumbList JSON-LD -->
    <script type="application/ld+json">
    {
        "@@context": "https://schema.org",
        "@type": "BreadcrumbList",
        "itemListElement": [
            @foreach($trail as $index => $crumb)
            {
                "@type": "ListItem",
                "position": {{ $index + 1 }},
                "name": {!! json_encode($crumb['title']) !!},
                "item": {!! json_encode($crumb['url']) !!}
            }{{ $loop->last ? '' : ',' }}
            @endforeach
        ]
    }
    </script>
@endif
