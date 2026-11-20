@if($p['image_url'] !== '')
<figure class="cms-image">
    @if($p['link_url'] !== '')<a href="{{ $p['link_url'] }}">@endif
    <img src="{{ $p['image_url'] }}" alt="{{ $p['image_alt'] }}" loading="lazy" decoding="async" @if($imageWidth > 0) width="{{ $imageWidth }}" @endif @if($imageHeight > 0) height="{{ $imageHeight }}" @endif style="object-fit: {{ $imageFit }}">
    @if($p['link_url'] !== '')</a>@endif
    @if($p['caption'] !== '')<figcaption>{{ $p['caption'] }}</figcaption>@endif
</figure>
@endif
