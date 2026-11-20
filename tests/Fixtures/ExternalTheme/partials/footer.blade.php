<footer><p>{{ $brandDescription }}</p>
@foreach(($footerQuick ?: $frontFallbackLinks) as $item)<a href="{{ $item['url'] }}">{{ $item['title'] }}</a>@endforeach
@foreach(($footerLegal ?: []) as $item)<a href="{{ $item['url'] }}">{{ $item['title'] }}</a>@endforeach
<button type="button" data-dispatch="open-cookie-settings">Cookies</button>
</footer>
