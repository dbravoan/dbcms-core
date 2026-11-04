<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Front-office filter UI (per-theme skins)
|--------------------------------------------------------------------------
| The filter panel has ONE functional implementation (markup, accessibility,
| canonical links, no-JS behaviour) and one visual skin per theme.
|
| A skin is a set of class tokens consumed by the panel and its control
| partials. Themes declare theirs in `themes/{theme}/filter.json`; anything a
| theme does not override falls back to the neutral defaults below, so the
| widget is never broken by a missing token.
|
| Tokens are pure presentation: they never change behaviour, so a theme can be
| as loud or as quiet as it wants without touching the filter logic.
|
| Behavioural keys:
|   icon       check | dot | plus | none   → option indicator
|   columns    1..4                         → groups grid (sm / lg)
|   collapse   bool                         → mobile collapsible panel
|   maxVisible int                          → options before "show all"
*/

return [
    'default' => [
        // Behaviour
        'icon' => 'check',
        'columns' => 3,
        'collapse' => true,
        'maxVisible' => 12,

        // Shell
        'panel' => 'rounded-xl border border-slate-200 bg-white',
        'accent' => '',
        'summary' => 'flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-3 md:hidden',
        'summaryTitle' => 'text-sm font-bold text-slate-900',
        'summaryBadge' => 'inline-flex min-w-5 items-center justify-center rounded-full bg-slate-900 px-1.5 py-0.5 text-[10px] font-bold text-white',
        'summaryChevron' => 'text-xs text-slate-400',
        'bodyWrap' => 'hidden p-4 pt-0 md:block md:p-5',
        'form' => 'space-y-5',
        'groupsWrap' => 'grid gap-5 sm:grid-cols-2 lg:grid-cols-3',

        // Groups
        'group' => 'space-y-2.5',
        'legend' => 'text-[11px] font-bold uppercase tracking-wider text-slate-500',
        'legendPrefix' => '',
        'legendPrefixClass' => 'mr-1.5 opacity-60',

        // Options (links that toggle a value)
        'options' => 'flex flex-wrap gap-1.5',
        'option' => 'inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs text-slate-600 transition-colors hover:border-slate-900 hover:text-slate-900',
        'optionActive' => 'inline-flex items-center gap-1.5 rounded-lg border border-slate-900 bg-slate-900 px-3 py-1.5 text-xs font-bold text-white',
        'box' => 'flex h-3.5 w-3.5 shrink-0 items-center justify-center rounded-[3px] border border-current',
        'boxCheck' => 'text-[8px]',
        'radio' => 'flex h-3.5 w-3.5 shrink-0 items-center justify-center rounded-full border border-current',
        'radioDot' => 'h-1.5 w-1.5 rounded-full bg-current',
        'moreWrap' => 'mt-2',
        'moreSummary' => 'cursor-pointer text-[11px] font-semibold text-slate-500 hover:text-slate-900',

        // Active chips
        'chipsWrap' => 'mb-3 flex flex-wrap items-center gap-2',
        'chip' => 'inline-flex items-center gap-1.5 rounded-full bg-slate-900 px-3 py-1 text-xs font-semibold text-white transition-opacity hover:opacity-90',
        'chipRemove' => 'text-[10px] opacity-70',
        'clearAll' => 'text-xs font-semibold text-slate-500 underline underline-offset-2 hover:text-slate-900',

        // Free-form controls
        'field' => 'w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-800 focus:border-slate-900 focus:outline-none',
        'numberRow' => 'flex items-center gap-2',
        'numberInput' => 'min-w-0 flex-1',
        'dash' => 'text-slate-400',
        'unit' => 'text-[11px] font-semibold uppercase text-slate-400',
        'switchRow' => 'inline-flex cursor-pointer items-center gap-2 text-xs text-slate-700',
        'switchInput' => 'rounded border-slate-300 text-slate-900 focus:ring-0',

        // Actions
        'submitWrap' => 'sticky bottom-0 z-10 -mx-4 mt-4 flex flex-wrap items-center gap-3 border-t border-slate-200 bg-white/95 px-4 py-3 backdrop-blur md:static md:mx-0 md:border-0 md:bg-transparent md:p-0',
        'submit' => 'inline-flex items-center gap-2 rounded-lg bg-slate-900 px-4 py-2 text-xs font-bold text-white transition-colors hover:bg-slate-800',
        'submitIcon' => 'text-[10px]',
    ],
];
