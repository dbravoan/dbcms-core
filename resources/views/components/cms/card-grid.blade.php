@props([
    'items' => [],
    'mapping' => [],
    'columns' => '3',
    'style' => 'cms-card',
    'gridClass' => '',
])

@php
    $itemsList = is_iterable($items) ? $items : [];

    $colsClass = match ((string) $columns) {
        '1' => 'grid-cols-1',
        '2' => 'grid-cols-1 md:grid-cols-2',
        '4' => 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-4',
        default => 'grid-cols-1 md:grid-cols-3',
    };

    $cardBaseStyle = match ($style) {
        'glass' => 'cms-card backdrop-blur-xl border cms-border-accent cms-hover-border-accent',
        'minimal' => 'cms-surface-2 border cms-border cms-hover-border-strong',
        default => 'cms-card cms-hover-border-accent',
    };
@endphp

<div class="grid {{ $colsClass }} gap-6 w-full {{ $gridClass }}">
    @forelse($itemsList as $item)
        @php
            $isObj = is_object($item);
            $getVal = function ($key) use ($item, $isObj, $mapping) {
                $mappedKey = $mapping[$key] ?? $key;
                if ($isObj) {
                    return $item->{$mappedKey} ?? ($item->{$key} ?? null);
                }
                return $item[$mappedKey] ?? ($item[$key] ?? null);
            };

            $title = $getVal('title');
            $subtitle = $getVal('subtitle') ?? $getVal('summary') ?? $getVal('description');
            $image = $getVal('image') ?? $getVal('featured_image') ?? $getVal('avatar');
            $icon = $getVal('icon');
            $badge = $getVal('badge') ?? $getVal('price');
            $meta = $getVal('meta') ?? $getVal('author');
            $url = $getVal('url') ?? $getVal('cta_url');
            $ctaLabel = $getVal('cta_label') ?? ($mapping['default_cta_label'] ?? null);
            $tags = $getVal('tags') ?? $getVal('features');
        @endphp

        <div class="{{ $cardBaseStyle }} p-5 sm:p-6 rounded-2xl flex flex-col justify-between transition-all duration-300 group">
            <div class="space-y-4">
                {{-- Media / Icon Header --}}
                @if(!empty($image))
                    <div class="w-full h-44 rounded-xl overflow-hidden bg-black relative border cms-border">
                        <img src="{{ $image }}" alt="{{ $title ?? 'Card Image' }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                        @if(!empty($badge))
                            <span class="absolute top-3 right-3 px-2.5 py-1 rounded-full text-[10px] font-bold bg-black/80 backdrop-blur-md cms-accent border cms-border-accent font-mono">
                                {{ $badge }}
                            </span>
                        @endif
                    </div>
                @elseif(!empty($icon))
                    <div class="flex items-center justify-between">
                        <div class="w-12 h-12 rounded-xl cms-surface-2 border cms-border-accent flex items-center justify-center text-xl cms-accent">
                            @if(str_contains($icon, 'fa-'))
                                <i class="{{ $icon }}"></i>
                            @else
                                {{ $icon }}
                            @endif
                        </div>
                        @if(!empty($badge))
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold cms-accent-soft cms-accent border cms-border-accent font-mono">
                                {{ $badge }}
                            </span>
                        @endif
                    </div>
                @elseif(!empty($badge))
                    <div class="flex justify-end">
                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold cms-accent-soft cms-accent border cms-border-accent font-mono">
                            {{ $badge }}
                        </span>
                    </div>
                @endif

                {{-- Title & Subtitle --}}
                <div>
                    @if(!empty($meta))
                        <div class="text-[11px] font-mono text-gray-500 mb-1 flex items-center gap-2">
                            <span>{{ $meta }}</span>
                        </div>
                    @endif

                    @if(!empty($title))
                        <h3 class="text-base sm:text-lg font-bold cms-text group-cms-hover-accent transition-colors leading-snug">
                            {{ $title }}
                        </h3>
                    @endif

                    @if(!empty($subtitle))
                        <div class="text-xs cms-text-muted mt-2 font-mono leading-relaxed line-clamp-3">
                            {!! is_string($subtitle) ? nl2br(e($subtitle)) : '' !!}
                        </div>
                    @endif
                </div>

                {{-- Feature Tags / Bullet list (if any) --}}
                @if(is_array($tags) && count($tags) > 0)
                    <ul class="space-y-1.5 pt-2 border-t cms-border/80 font-mono text-xs cms-text-muted">
                        @foreach($tags as $t)
                            <li class="flex items-center gap-2">
                                <span class="cms-accent text-xs"><i class="fa-solid fa-check"></i></span>
                                <span>{{ is_string($t) ? $t : ($t['name'] ?? json_encode($t)) }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            {{-- Card Footer / Action Button --}}
            @if(!empty($url))
                <div class="pt-5 mt-4 border-t cms-border/60 flex items-center justify-between">
                    <a href="{{ $url }}" class="text-xs font-bold cms-accent hover:underline font-mono inline-flex items-center gap-1.5">
                        <span>{{ $ctaLabel ?: 'Explorar' }}</span>
                        <span class="transform group-hover:translate-x-1 transition-transform">&rarr;</span>
                    </a>
                </div>
            @endif
        </div>
    @empty
        <div class="col-span-full py-12 text-center text-gray-500 font-mono text-xs cms-card">
            &gt; No hay elementos disponibles para mostrar en este listado.
        </div>
    @endforelse
</div>
