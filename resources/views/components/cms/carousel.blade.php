@props([
    'slides' => [],
    'title' => '',
    'height' => 520,
    'autoplay' => true,
    'interval' => 6000,
    'showArrows' => true,
    'showDots' => true,
])

@php
    /**
     * Full-width slideshow.
     *
     * Themes may override it through `FrontViewResolver::block('cms.carousel')`.
     * Without JavaScript the first slide is still fully readable (the slides are
     * plain markup, only visibility is toggled).
     */
    $slides = array_values(array_filter((array) $slides, static fn ($slide): bool => is_array($slide)));
    $count = count($slides);
    $uid = 'carousel-'.substr(md5(implode('|', array_map(static fn (array $s): string => (string) ($s['image_url'] ?? ''), $slides)).$title), 0, 6);

    $esc = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
@endphp

@if($count > 0)
    <section
        id="{{ $uid }}"
        class="cms-carousel relative w-full overflow-hidden font-mono"
        style="--cms-carousel-height: {{ (int) $height }}px;"
        x-data="{
            active: 0,
            total: {{ $count }},
            autoplay: @js((bool) $autoplay),
            interval: {{ (int) $interval }},
            timer: null,
            init() {
                if (!this.autoplay || this.total < 2 || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                    return;
                }
                this.start();
            },
            start() {
                this.stop();
                this.timer = setInterval(() => this.next(), this.interval);
            },
            stop() {
                if (this.timer) { clearInterval(this.timer); this.timer = null; }
            },
            go(index) {
                this.active = (index + this.total) % this.total;
                if (this.autoplay) { this.start(); }
            },
            next() { this.go(this.active + 1); },
            prev() { this.go(this.active - 1); }
        }"
        @mouseenter="stop()"
        @mouseleave="if (autoplay) start()"
        aria-roledescription="carousel"
        aria-label="{{ $title !== '' ? $esc($title) : 'Carousel' }}"
    >
        @if($title !== '')
            <div class="absolute top-0 inset-x-0 z-20 px-6 py-4 pointer-events-none">
                <h2 class="text-sm sm:text-base font-black cms-text drop-shadow-lg">&gt;_ {{ $esc($title) }}</h2>
            </div>
        @endif

        @foreach($slides as $index => $slide)
            @php
                $image = $esc($slide['image_url'] ?? '');
                $slideTitle = $esc($slide['title'] ?? '');
                $subtitle = $esc($slide['subtitle'] ?? '');
                $ctaText = $esc($slide['cta_text'] ?? '');
                $ctaUrl = (string) ($slide['cta_url'] ?? '');
            @endphp

            <div class="cms-carousel-slide absolute inset-0 transition-opacity duration-700 ease-out"
                 x-show="active === {{ $index }}"
                 @if($index !== 0) x-cloak @endif
                 :class="active === {{ $index }} ? 'opacity-100 z-10' : 'opacity-0 z-0'"
                 aria-hidden="{{ $index === 0 ? 'false' : 'true' }}"
                 role="group"
                 aria-roledescription="slide"
                 aria-label="{{ $index + 1 }} / {{ $count }}">
                @if($image !== '')
                    <img src="{{ $image }}" alt="{{ $slideTitle }}" loading="{{ $index === 0 ? 'eager' : 'lazy' }}"
                         class="absolute inset-0 w-full h-full object-cover">
                @endif
                <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/40 to-black/10"></div>

                @if($slideTitle !== '' || $subtitle !== '' || $ctaText !== '')
                    <div class="relative z-10 h-full flex items-center">
                        <div class="max-w-3xl px-6 sm:px-12 py-16 space-y-4">
                            @if($slideTitle !== '')
                                <h3 class="text-2xl sm:text-4xl font-black cms-text leading-tight drop-shadow-xl">{{ $slideTitle }}</h3>
                            @endif
                            @if($subtitle !== '')
                                <p class="text-sm sm:text-base text-gray-200 max-w-2xl drop-shadow-lg">{{ $subtitle }}</p>
                            @endif
                            @if($ctaText !== '' && $ctaUrl !== '')
                                <a href="{{ $ctaUrl }}"
                                   class="inline-flex items-center gap-2 mt-2 px-5 py-2.5 rounded-lg cms-accent-bg hover:bg-[#00d97f] text-black font-bold text-sm transition-colors">
                                    <span>{{ $ctaText }}</span>
                                    <span aria-hidden="true">&rarr;</span>
                                </a>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        @endforeach

        @if($count > 1 && $showArrows)
            <button type="button" @click="prev()"
                    class="absolute left-3 top-1/2 -translate-y-1/2 z-30 w-10 h-10 rounded-full bg-black/50 cms-hover-accent-bg cms-text border border-white/20 cms-hover-border-accent flex items-center justify-center transition-colors"
                    aria-label="{{ __('admin.block_manager.carousel_prev') ?? 'Anterior' }}">
                <span aria-hidden="true">&#8249;</span>
            </button>
            <button type="button" @click="next()"
                    class="absolute right-3 top-1/2 -translate-y-1/2 z-30 w-10 h-10 rounded-full bg-black/50 cms-hover-accent-bg cms-text border border-white/20 cms-hover-border-accent flex items-center justify-center transition-colors"
                    aria-label="{{ __('admin.block_manager.carousel_next') ?? 'Siguiente' }}">
                <span aria-hidden="true">&#8250;</span>
            </button>
        @endif

        @if($count > 1 && $showDots)
            <div class="absolute bottom-4 inset-x-0 z-30 flex items-center justify-center gap-2" role="tablist">
                @foreach($slides as $index => $slide)
                    <button type="button" @click="go({{ $index }})"
                            class="w-2.5 h-2.5 rounded-full transition-all"
                            :class="active === {{ $index }} ? 'cms-accent-bg scale-125' : 'bg-white/50 hover:bg-white'"
                            role="tab"
                            :aria-selected="active === {{ $index }} ? 'true' : 'false'"
                            aria-label="{{ $index + 1 }}"></button>
                @endforeach
            </div>
        @endif
    </section>
@endif
