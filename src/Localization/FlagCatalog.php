<?php

declare(strict_types=1);

namespace DbCms\Core\Localization;

/**
 * Flag emoji catalog shared by markets, languages and the backoffice picker.
 *
 * A flag emoji is derived from any ISO-3166 alpha-2 code (regional indicator
 * symbols), so no hardcoded emoji table is needed; the label is resolved with
 * the intl extension in the requested locale. When there is no code — or a code
 * is not a country — the neutral globe `🌐` is used, which is also offered as an
 * explicit option for international markets or groups of countries.
 */
final class FlagCatalog
{
    public const GLOBE = '🌐';

    /**
     * Resolve the flag emoji for a country code, or null when not derivable.
     */
    public static function emoji(string $code): ?string
    {
        $code = strtoupper(trim($code));

        if (preg_match('/^[A-Z]{2}$/', $code) !== 1) {
            return null;
        }

        $emoji = '';

        foreach (str_split($code) as $letter) {
            $emoji .= mb_chr(0x1F1E6 + (ord($letter) - 65), 'UTF-8');
        }

        return $emoji;
    }

    /**
     * Flag for a market/language code, falling back to the globe.
     */
    public static function forCode(?string $code): string
    {
        if ($code === null || trim($code) === '') {
            return self::GLOBE;
        }

        return self::emoji($code) ?? self::GLOBE;
    }

    /**
     * Flag for a *language* code.
     *
     * Language codes are not country codes (`en`, `ca`, `ar`…), so an explicit
     * map is consulted first; otherwise the country derivation and finally the
     * globe are used.
     */
    public static function forLanguage(?string $code): string
    {
        $code = strtolower(trim((string) $code));

        if ($code === '') {
            return self::GLOBE;
        }

        /** @var array<string, string> $map */
        $map = (array) config('flags.language_flags', []);

        return $map[$code] ?? self::forCode($code);
    }

    /**
     * Localized country name for an ISO code (falls back to the code itself).
     */
    public static function label(string $code, ?string $locale = null): string
    {
        $code = strtoupper(trim($code));

        if ($code === '') {
            return self::GLOBE;
        }

        $locale = $locale ?? app()->getLocale();

        if (class_exists(\Locale::class)) {
            $region = \Locale::getDisplayRegion($locale.'_'.$code, $locale);

            if (is_string($region) && $region !== '' && $region !== $code) {
                return $region;
            }
        }

        return $code;
    }

    /**
     * Options for the backoffice picker: the neutral globe first, then the
     * supranational groups, then every country offered in the config.
     *
     * @return array<int, array{value: string, code: string, label: string, group: string}>
     */
    public static function options(?string $locale = null): array
    {
        $locale = $locale ?? app()->getLocale();
        $international = self::term('flag_international', 'Internacional', $locale);
        $countries = self::term('flag_countries', 'Países y regiones', $locale);

        $options = [[
            'value' => self::GLOBE,
            'code' => '',
            'label' => $international,
            'group' => $international,
        ]];

        /** @var array<string, array<string, string>> $groups */
        $groups = (array) config('flags.groups', []);

        foreach ($groups as $emoji => $translations) {
            $options[] = [
                'value' => (string) $emoji,
                'code' => '',
                'label' => (string) ($translations[$locale] ?? reset($translations) ?: $emoji),
                'group' => $international,
            ];
        }

        /** @var array<int, string> $codes */
        $codes = (array) config('flags.codes', []);

        foreach ($codes as $code) {
            $emoji = self::emoji($code);

            if ($emoji === null) {
                continue;
            }

            $options[] = [
                'value' => $emoji,
                'code' => strtoupper($code),
                'label' => self::label($code, $locale),
                'group' => $countries,
            ];
        }

        return $options;
    }

    private static function term(string $key, string $fallback, string $locale): string
    {
        $translationKey = 'admin.markets.'.$key;
        $value = __($translationKey, [], $locale);

        return is_string($value) && $value !== $translationKey ? $value : $fallback;
    }
}
