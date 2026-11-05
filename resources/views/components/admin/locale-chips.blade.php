@props([
    'locales' => [],
    'empty' => '—',
])

@php
    $localeList = collect($locales)
        ->filter(static fn ($locale): bool => is_string($locale) && $locale !== '')
        ->unique()
        ->values();
@endphp

@if($localeList->isEmpty())
    <span class="text-[10px] text-[color:var(--admin-muted)] font-mono">{{ $empty }}</span>
@else
    <div class="flex flex-wrap items-center gap-1">
        @foreach($localeList as $locale)
            <x-admin.chip variant="info">{{ strtoupper((string) $locale) }}</x-admin.chip>
        @endforeach
    </div>
@endif
