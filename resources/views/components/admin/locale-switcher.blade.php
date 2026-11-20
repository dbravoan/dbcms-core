@props([
    'align' => 'right',
    'variant' => 'dark',
])

@php
    $available = collect($availableAdminLocales ?? config('cms.admin_locales', ['es', 'en']))
        ->map(static fn ($locale): string => strtolower(trim((string) $locale)))
        ->filter()
        ->unique()
        ->values()
        ->all();

    $current = strtolower((string) ($currentAdminLocale ?? app()->getLocale()));

    $flags = [];
    foreach ($available as $code) {
        $flags[$code] = class_exists(\DbCms\Core\Localization\FlagCatalog::class)
            ? \DbCms\Core\Localization\FlagCatalog::forLanguage($code)
            : '🌐';
    }

    // The switcher is used on both the dark (dbCMS) and the light (termoCMS) chrome.
    $isLight = $variant === 'light';

    $labelClass = $isLight
        ? 'text-[11px] font-semibold text-slate-400'
        : 'text-[11px] font-semibold text-gray-500';

    $groupClass = $isLight
        ? 'inline-flex items-center gap-0.5 rounded-lg border border-slate-200 bg-white p-0.5 shadow-sm'
        : 'inline-flex items-center gap-0.5 rounded-lg border border-gray-700 bg-[#1A1A1A] p-0.5';

    $buttonClass = $isLight
        ? 'inline-flex items-center gap-1.5 rounded-md px-1.5 sm:px-2.5 py-1 text-[11px] font-bold text-slate-500 hover:text-slate-900 hover:bg-slate-100 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-900/40'
        : 'inline-flex items-center gap-1.5 rounded-md px-1.5 sm:px-2.5 py-1 text-[11px] font-bold text-gray-400 hover:text-white hover:bg-[#232323] transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-[#00FF99]/50';

    $activeClass = $isLight
        ? 'bg-slate-900 text-white hover:bg-slate-900 hover:text-white'
        : 'bg-[#00FF99] text-black hover:bg-[#00FF99] hover:text-black';

    $selectClass = $isLight
        ? 'bg-white border border-slate-200 text-slate-700 rounded-lg px-2 py-1 text-xs font-bold cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-900/40'
        : 'bg-[#1A1A1A] border border-gray-700 text-gray-200 rounded-lg px-2 py-1 text-xs font-bold cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-[#00FF99]/50';
@endphp

@if(count($available) > 1)
    <form method="POST" action="{{ route('admin.locale.update') }}"
          class="inline-flex items-center gap-2"
          title="{{ __('admin.locale.switch') }}">
        @csrf
        <span class="hidden lg:inline {{ $labelClass }}">{{ __('admin.locale.label') }}</span>

        @if(count($available) <= 4)
            {{-- Segmented control: works without JavaScript (each option is a submit). --}}
            <div class="{{ $groupClass }}" role="group" aria-label="{{ __('admin.locale.switch') }}">
                @foreach($available as $locale)
                    <button type="submit"
                            name="locale"
                            value="{{ $locale }}"
                            title="{{ strtoupper($locale) }}"
                            aria-pressed="{{ $locale === $current ? 'true' : 'false' }}"
                            @class([$buttonClass, $activeClass => $locale === $current])>
                        <span class="hidden sm:inline" aria-hidden="true">{{ $flags[$locale] }}</span>
                        <span>{{ strtoupper($locale) }}</span>
                    </button>
                @endforeach
            </div>
        @else
            <label for="admin-locale-select" class="admin-field-label sr-only">{{ __('admin.locale.label') }}</label>
            <select id="admin-locale-select"
                    name="locale"
                    data-auto-submit
                    class="admin-control {{ $selectClass }}">
                @foreach($available as $locale)
                    <option value="{{ $locale }}" @selected($locale === $current)>
                        {{ $flags[$locale] }} {{ strtoupper($locale) }}
                    </option>
                @endforeach
            </select>
            <noscript>
                <button type="submit" class="{{ $buttonClass }}">
                    {{ __('admin.actions.apply') }}
                </button>
            </noscript>
        @endif
    </form>
@endif
