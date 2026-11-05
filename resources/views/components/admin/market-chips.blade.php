@props([
    'markets' => [],
    'empty' => '—',
])

@php
    $marketList = $markets instanceof \Illuminate\Support\Collection ? $markets : collect($markets);
@endphp

@if($marketList->isEmpty())
    <span class="text-[10px] text-[color:var(--admin-muted)] font-mono">{{ $empty }}</span>
@else
    <div class="flex flex-wrap items-center gap-1">
        @foreach($marketList as $market)
            <x-admin.chip variant="market" :title="$market->name ?? null">{{ strtoupper((string) ($market->code ?? '')) }}</x-admin.chip>
        @endforeach
    </div>
@endif
