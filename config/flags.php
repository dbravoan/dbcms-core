<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Flags & emoji catalog
|--------------------------------------------------------------------------
| Drives the backoffice emoji picker used by markets and languages. The flag
| emoji itself is derived from the ISO-3166 alpha-2 code (so any code works),
| and the label comes from the intl extension in the active locale.
|
| `globe` is the neutral option for international markets / groups of
| countries; `groups` adds supranational flags next to it.
*/

return [
    // Neutral "international / worldwide" option.
    'globe' => '🌐',

    // Supranational / non-country options offered alongside the globe.
    'groups' => [
        '🇪🇺' => ['es' => 'Unión Europea', 'en' => 'European Union'],
        '🇺🇳' => ['es' => 'Naciones Unidas', 'en' => 'United Nations'],
        '🇬🇧' => ['es' => 'Reino Unido', 'en' => 'United Kingdom'],
        '🏴' => ['es' => 'Idioma / sin país', 'en' => 'Language / stateless'],
        '🏳️' => ['es' => 'Internacional (bandera blanca)', 'en' => 'International (white flag)'],
    ],

    /*
    | Language code → emoji used when the language has no flag assigned.
    | Language codes are not country codes (`en`, `ca`, `ar`…), so they cannot be
    | derived: this map gives a sensible default before falling back to the
    | country derivation and finally the globe.
    */
    'language_flags' => [
        'es' => '🇪🇸', 'en' => '🇺🇸', 'ca' => '🏴', 'gl' => '🏴', 'eu' => '🏴',
        'pt' => '🇵🇹', 'fr' => '🇫🇷', 'de' => '🇩🇪', 'it' => '🇮🇹', 'nl' => '🇳🇱',
        'sv' => '🇸🇪', 'da' => '🇩🇰', 'fi' => '🇫🇮', 'no' => '🇳🇴', 'is' => '🇮🇸',
        'pl' => '🇵🇱', 'cs' => '🇨🇿', 'sk' => '🇸🇰', 'hu' => '🇭🇺', 'ro' => '🇷🇴',
        'bg' => '🇧🇬', 'el' => '🇬🇷', 'hr' => '🇭🇷', 'sl' => '🇸🇮', 'sr' => '🇷🇸',
        'ru' => '🇷🇺', 'uk' => '🇺🇦', 'tr' => '🇹🇷', 'ar' => '🇸🇦', 'he' => '🇮🇱',
        'fa' => '🇮🇷', 'hi' => '🇮🇳', 'bn' => '🇧🇩', 'ur' => '🇵🇰', 'zh' => '🇨🇳',
        'ja' => '🇯🇵', 'ko' => '🇰🇷', 'th' => '🇹🇭', 'vi' => '🇻🇳', 'id' => '🇮🇩',
        'ms' => '🇲🇾', 'fil' => '🇵🇭', 'sw' => '🇰🇪', 'af' => '🇿🇦',
    ],

    /*
    | Country codes offered in the picker. Any other ISO code can still be typed
    | by hand: the emoji is generated from the code.
    */
    'codes' => [
        // Europe
        'es', 'pt', 'fr', 'de', 'it', 'nl', 'be', 'lu', 'ie', 'gb', 'ch', 'at',
        'se', 'no', 'dk', 'fi', 'is', 'pl', 'cz', 'sk', 'hu', 'ro', 'bg', 'gr',
        'hr', 'si', 'rs', 'ua', 'ru', 'tr', 'ee', 'lv', 'lt', 'mt', 'cy',
        // Americas
        'us', 'ca', 'mx', 'br', 'ar', 'cl', 'co', 'pe', 've', 'ec', 'uy', 'py',
        'bo', 'cr', 'pa', 'do', 'gt', 'hn', 'sv', 'ni', 'cu', 'pr',
        // Africa
        'ma', 'dz', 'tn', 'eg', 'ng', 'za', 'ke', 'gh', 'et', 'sn', 'ci', 'cm',
        'ao', 'mz', 'tz', 'ug', 'zm', 'zw',
        // Asia & Middle East
        'il', 'sa', 'ae', 'qa', 'kw', 'bh', 'om', 'jo', 'lb', 'ir', 'iq', 'in',
        'pk', 'bd', 'lk', 'cn', 'jp', 'kr', 'tw', 'hk', 'sg', 'my', 'id', 'th',
        'vn', 'ph', 'kh', 'mm',
        // Oceania
        'au', 'nz',
    ],
];
