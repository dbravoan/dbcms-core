<div data-cms-carousel class="cms-carousel" role="region" aria-roledescription="{{ __('Carrusel') }}" aria-label="{{ $p['label'] ?: __('Carrusel') }}" data-autoplay="{{ $p['autoplay'] ? 'true' : 'false' }}" data-interval="{{ $p['interval'] }}" data-loop="{{ $p['loop'] ? 'true' : 'false' }}" data-slides-per-view="{{ $p['slides_per_view'] }}" data-slides-per-view-tablet="{{ $p['slides_per_view_tablet'] }}" data-slides-per-view-mobile="{{ $p['slides_per_view_mobile'] }}" data-slides-per-group="{{ $p['slides_per_group'] }}" data-slides-per-group-tablet="{{ $p['slides_per_group_tablet'] }}" data-slides-per-group-mobile="{{ $p['slides_per_group_mobile'] }}" data-gap="{{ $gap }}">
    <div class="swiper" data-carousel-track><div class="swiper-wrapper">
    @foreach($p['items'] as $index => $item)<div class="swiper-slide" role="group" aria-roledescription="{{ __('Diapositiva') }}" aria-label="{{ $item['label'] ?? ($index + 1) }}">{!! $item['content_html'] ?? '' !!}</div>@endforeach
    </div></div>
    <div data-carousel-controls hidden>
    @if($p['show_arrows'])<button type="button" data-carousel-prev aria-label="{{ __('Diapositiva anterior') }}">←</button><button type="button" data-carousel-next aria-label="{{ __('Diapositiva siguiente') }}">→</button>@endif
    @if($p['show_dots'])<div data-carousel-pagination></div>@endif
    @if($p['autoplay'])<button type="button" data-carousel-pause data-label-pause="{{ __('Pausar carrusel') }}" data-label-play="{{ __('Reproducir carrusel') }}" aria-pressed="false">{{ __('Pausar carrusel') }}</button>@endif
    </div>
</div>
