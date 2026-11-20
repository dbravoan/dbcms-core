<{{ $listTag }} class="cms-list">
@foreach($p['items'] as $item)
    <li>@if(($item['url'] ?? '') !== '')<a href="{{ $item['url'] }}">{{ $item['text'] ?? '' }}</a>@else{{ $item['text'] ?? '' }}@endif</li>
@endforeach
</{{ $listTag }}>
