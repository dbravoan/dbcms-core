<figure class="cms-quote"><blockquote><p>{{ $p['quote'] }}</p></blockquote>
@if($p['author'] !== '' || $p['source_label'] !== '')<figcaption>{{ $p['author'] }} @if($p['source_url'] !== '')<a href="{{ $p['source_url'] }}"><cite>{{ $p['source_label'] }}</cite></a>@elseif($p['source_label'] !== '')<cite>{{ $p['source_label'] }}</cite>@endif</figcaption>@endif
</figure>
