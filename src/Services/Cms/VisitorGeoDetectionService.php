<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Cms;

use DbCms\Core\Contracts\Services\LocalizationServiceInterface;
use DbCms\Core\Models\Language;
use DbCms\Core\Models\Market;
use Illuminate\Http\Request;

class VisitorGeoDetectionService
{
    /**
     * Country to Market mapping dictionary.
     *
     * @var array<string, string>
     */
    protected array $countryMarketMap = [
        'ES' => 'es',
        'AD' => 'es',
        'US' => 'us',
        'CA' => 'us',
        'GB' => 'us',
        'MX' => 'mx',
        'CO' => 'mx',
        'AR' => 'mx',
        'CL' => 'mx',
        'PE' => 'mx',
    ];

    /**
     * Detect country code from incoming request headers or fallback.
     */
    public function detectCountry(Request $request): string
    {
        // 1. Cloudflare IPCountry header
        $cfCountry = (string) $request->header('CF-IPCountry');
        if (! empty($cfCountry) && $cfCountry !== 'XX' && $cfCountry !== 'T1') {
            return strtoupper($cfCountry);
        }

        // 2. Standard GeoIP headers
        $geoHeaders = ['X-Country-Code', 'GEOIP_COUNTRY_CODE', 'X-Geo-Country'];
        foreach ($geoHeaders as $header) {
            $val = (string) $request->header($header);
            if (! empty($val)) {
                return strtoupper($val);
            }
        }

        // 3. Inspect server variables
        if (! empty($_SERVER['HTTP_CF_IPCOUNTRY'])) {
            return strtoupper((string) $_SERVER['HTTP_CF_IPCOUNTRY']);
        }

        return 'ES'; // Default fallback country
    }

    /**
     * Detect browser language code from Accept-Language header.
     */
    public function detectBrowserLanguage(Request $request): string
    {
        // Only languages configured in the backoffice are accepted: creating a
        // new one makes the detector react without touching this list.
        $active = app(LocalizationServiceInterface::class)->activeLocales();

        $languages = $request->getLanguages();
        if (! empty($languages)) {
            $primary = (string) $languages[0];
            // Normalize e.g. "es_ES" or "es-ES" to "es"
            $code = strtolower(substr($primary, 0, 2));
            if (in_array($code, $active, true)) {
                return $code;
            }

            // A regional variant ("ca-ES") may match another accepted code.
            $full = strtolower(str_replace('_', '-', $primary));
            foreach ($active as $locale) {
                if (str_starts_with($full, $locale.'-')) {
                    return $locale;
                }
            }
        }

        return app(LocalizationServiceInterface::class)->defaultLocale();
    }

    /**
     * Resolve recommended Market and Language models for the visitor.
     *
     * @return array{market: Market, language: Language, country: string, locale: string}
     */
    public function getRecommendation(Request $request): array
    {
        $country = $this->detectCountry($request);
        $preferredLang = $this->detectBrowserLanguage($request);

        // Find market matching detected country
        $targetMarketCode = $this->countryMarketMap[$country] ?? 'es';
        $market = Market::where('code', $targetMarketCode)->where('is_active', true)->first()
            ?? Market::where('is_default', true)->where('is_active', true)->first()
            ?? Market::firstOrCreate(
                ['code' => 'es'],
                ['name' => 'Spain Market', 'currency' => 'EUR', 'default_locale' => 'es', 'is_default' => true, 'is_active' => true]
            );

        // Find language matching preference in this market
        $language = $market->languages()->where('code', $preferredLang)->where('is_active', true)->first()
            ?? $market->languages()->where('code', $market->default_locale)->where('is_active', true)->first()
            ?? Language::where('code', $preferredLang)->where('is_active', true)->first()
            ?? Language::where('is_default', true)->where('is_active', true)->first()
            ?? Language::firstOrCreate(
                ['code' => 'es'],
                ['name' => 'Spanish', 'native_name' => 'Español', 'direction' => 'ltr', 'is_default' => true, 'is_active' => true]
            );

        return [
            'market' => $market,
            'language' => $language,
            'country' => $country,
            'locale' => $language->code,
        ];
    }
}
