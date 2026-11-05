@php
    $geoChoices = $geoMarketChoices ?? [];
    $hasMultipleMarkets = count($geoChoices) > 1;
    $geoSelector = [
        'markets' => $geoChoices,
        'autoShow' => !empty($showGeoModal),
        'currentMarket' => $currentMarket?->code ?? '',
        'currentLocale' => $currentLocale,
        'recommendedMarket' => $recommendedMarket->code ?? '',
        'recommendedLocale' => $recommendedLanguage->code ?? '',
    ];

    $activeTheme = (string) config('cms.theme', 'db-cyber');
    $themeAccentText = match($activeTheme) {
        'db-editorial' => 'text-amber-800 font-bold',
        'db-enterprise' => 'text-indigo-600 font-bold',
        'db-minimal' => 'text-black underline font-bold',
        'db-sunset' => 'text-rose-400 font-bold',
        'db-brutalist' => 'text-black bg-yellow-300 px-1 font-black',
        default => 'text-[#00FF99]',
    };

    if (!$hasMultipleMarkets) {
        $modalTitle = __('front.geo.title');
        $modalDesc = __('front.geo.detected', ['class' => $themeAccentText, 'country' => e($detectedCountry ?? 'Global')]);
    } else {
        $modalTitle = __('front.geo.title_multi');
        $modalDesc = __('front.geo.detected_multi', ['class' => $themeAccentText, 'country' => e($detectedCountry ?? 'Global')]);
    }

    $marketLabel = __('front.geo.market_label');
    $languageLabel = __('front.geo.language_label');
    $stayText = __('front.geo.stay');
    $confirmText = __('front.geo.confirm');

    $themeModalClass = match($activeTheme) {
        'db-editorial' => 'bg-white text-stone-900 border border-stone-300 rounded-lg shadow-xl font-serif',
        'db-enterprise' => 'bg-white text-slate-900 border border-slate-200 rounded-xl shadow-2xl font-sans',
        'db-minimal' => 'bg-white text-black border border-black rounded-none shadow-none font-mono',
        'db-sunset' => 'bg-stone-900/90 backdrop-blur-xl text-white border border-rose-500/30 rounded-2xl shadow-[0_0_30px_rgba(244,63,94,0.15)] font-sans',
        'db-brutalist' => 'bg-yellow-100 text-black border-4 border-black rounded-none shadow-[6px_6px_0px_0px_#000] font-mono',
        default => 'bg-[#141414] border-2 border-[#00FF99] rounded-xl shadow-[0_0_35px_rgba(0,255,153,0.3)] text-white font-mono',
    };
    $themeSelectClass = match($activeTheme) {
        'db-editorial' => 'w-full bg-stone-50 border border-stone-300 rounded px-2.5 py-1.5 text-xs text-stone-900',
        'db-enterprise' => 'w-full bg-slate-50 border border-slate-300 focus:border-indigo-600 rounded-lg px-2.5 py-1.5 text-xs text-slate-900',
        'db-minimal' => 'w-full bg-white border border-black rounded-none px-2.5 py-1.5 text-xs text-black',
        'db-sunset' => 'w-full bg-black/40 border border-rose-500/30 focus:border-rose-400 rounded-lg px-2.5 py-1.5 text-xs text-white',
        'db-brutalist' => 'w-full bg-white border-2 border-black rounded-none px-2.5 py-1.5 text-xs text-black font-bold',
        default => 'w-full bg-[#1A1A1A] border border-gray-700 focus:border-[#00FF99] rounded px-2.5 py-1.5 text-xs text-white',
    };
    $themeBtnClass = match($activeTheme) {
        'db-editorial' => 'bg-stone-900 text-white hover:bg-stone-800 text-xs py-1.5 px-3 rounded font-sans',
        'db-enterprise' => 'bg-indigo-600 text-white hover:bg-indigo-700 text-xs py-1.5 px-3 rounded-lg font-medium shadow-sm',
        'db-minimal' => 'bg-black text-white hover:bg-neutral-800 text-xs py-1.5 px-3 rounded-none uppercase tracking-wider',
        'db-sunset' => 'bg-gradient-to-r from-rose-500 to-amber-500 hover:from-rose-600 hover:to-amber-600 text-white text-xs py-1.5 px-3 rounded-lg font-medium shadow-md',
        'db-brutalist' => 'bg-[#00F0FF] text-black border-2 border-black hover:bg-cyan-300 text-xs py-1.5 px-3 font-black shadow-[3px_3px_0px_0px_#000]',
        default => 'btn-neon text-xs py-1.5 px-3',
    };
@endphp

@if($geoChoices !== [])
<div x-data="visitorGeoSelector(JSON.parse($el.dataset.geoSelector))"
     data-geo-selector="{{ json_encode($geoSelector, JSON_THROW_ON_ERROR) }}"
     x-show="showModal"
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0 translate-y-4"
     x-transition:enter-end="opacity-100 translate-y-0"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="opacity-100 translate-y-0"
     x-transition:leave-end="opacity-0 translate-y-4"
     class="fixed bottom-6 right-6 z-50 max-w-md w-full p-4 font-mono text-xs select-none"
     style="display: none;">

    <div class="{{ $themeModalClass }} p-5 space-y-4 relative">
        <!-- Close button -->
        <button type="button" @click="stayCurrent()" class="absolute top-3 right-3 opacity-60 hover:opacity-100 text-base font-bold">&times;</button>

        <!-- Header -->
        <div class="flex items-center gap-2">
            <span class="text-base"><i class="fa-solid fa-globe"></i></span>
            <span class="font-bold tracking-wider text-sm">{{ $modalTitle }}</span>
        </div>

        <p class="opacity-80 text-xs leading-relaxed">
            {!! $modalDesc !!}
        </p>

        <!-- Selection Controls -->
        <div class="grid {{ $hasMultipleMarkets ? 'grid-cols-2' : 'grid-cols-1' }} gap-3 pt-1">
            @if($hasMultipleMarkets)
                <div>
                    <label class="block text-[10px] opacity-70 uppercase font-bold mb-1">{{ $marketLabel }}</label>
                    <select x-model="selectedMarket" @change="selectMarket($event.target.value)" class="{{ $themeSelectClass }}">
                        <template x-for="market in markets" :key="market.code">
                            <option :value="market.code" x-text="market.name + ' (' + market.code.toUpperCase() + ')'"></option>
                        </template>
                    </select>
                </div>
            @endif

            <div>
                <label class="block text-[10px] opacity-70 uppercase font-bold mb-1">{{ $languageLabel }}</label>
                <select x-model="selectedLocale" class="{{ $themeSelectClass }}">
                    <template x-for="language in languages" :key="language.code">
                        <option :value="language.code" x-text="language.name + ' (' + language.code.toUpperCase() + ')'"></option>
                    </template>
                </select>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex items-center justify-between gap-3 pt-2 border-t border-current/10">
            <button type="button" @click="stayCurrent()" class="opacity-60 hover:opacity-100 text-[11px] underline">
                {{ $stayText }}
            </button>

            <button type="button" @click="confirmSelection()" :disabled="!destination" class="{{ $themeBtnClass }}">
                {{ $confirmText }} &rarr;
            </button>
        </div>
    </div>
</div>
@endif
