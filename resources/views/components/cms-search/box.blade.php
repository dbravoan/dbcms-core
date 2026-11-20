@props([
    'action' => null,
    'value' => '',
    'size' => 'md',
    'variant' => 'light',
    'placeholder' => null,
    'autofocus' => false,
])

@php
    use DbCms\Core\Contracts\Services\LocalizationServiceInterface;

    // Default target: the general search hub of the **current** market + locale
    // (the middleware binds the resolved market for the request).
    if ($action === null) {
        try {
            $bound = app()->bound('current.market') ? app('current.market') : null;
            $currentMarket = $bound instanceof \DbCms\Core\Models\Market ? $bound : null;

            $action = app(LocalizationServiceInterface::class)
                ->frontUrl('search', '', app()->getLocale(), $currentMarket);
        } catch (\Throwable) {
            $action = url('/');
        }
    }

    $placeholder = $placeholder ?? __('Buscar en todo el sitio…');
    $isDark = $variant === 'dark';

    $inputClass = $size === 'lg'
        ? 'w-full rounded-xl py-3.5 pl-5 pr-28 text-sm shadow-sm '.($isDark
            ? 'border border-gray-700 bg-[#1A1A1A] text-gray-100 placeholder-gray-500 focus:border-[#00FF99] focus:ring-0'
            : 'border border-slate-200 bg-white text-slate-900 focus:border-slate-900 focus:ring-0')
        : 'w-full rounded-lg py-2.5 pl-4 pr-24 text-sm shadow-sm '.($isDark
            ? 'border border-gray-700 bg-[#1A1A1A] text-gray-100 placeholder-gray-500 focus:border-[#00FF99] focus:ring-0'
            : 'border border-slate-200 bg-white text-slate-900 focus:border-slate-900 focus:ring-0');

    $buttonClass = $size === 'lg'
        ? 'absolute inset-y-1.5 right-1.5 rounded-lg px-5 text-sm font-bold '.($isDark
            ? 'bg-[#00FF99] text-black hover:brightness-110'
            : 'bg-slate-900 text-white hover:bg-slate-800')
        : 'absolute inset-y-1 right-1 rounded-md px-4 text-xs font-bold '.($isDark
            ? 'bg-[#00FF99] text-black hover:brightness-110'
            : 'bg-slate-900 text-white hover:bg-slate-800');
@endphp

<form method="GET" action="{{ $action }}" role="search" {{ $attributes->merge(['class' => 'relative block']) }}>
    <label for="{{ $id = 'cms-search-'.uniqid() }}" class="sr-only">{{ __('Buscar') }}</label>

    <input id="{{ $id }}"
           type="search"
           name="q"
           value="{{ $value }}"
           placeholder="{{ $placeholder }}"
           @if($autofocus) autofocus @endif
           class="{{ $inputClass }}">

    <button type="submit" class="{{ $buttonClass }}">
        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
        <span class="sr-only">{{ __('Buscar') }}</span>
    </button>
</form>
