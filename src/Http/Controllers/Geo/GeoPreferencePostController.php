<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Controllers\Geo;

use DbCms\Core\Contracts\Services\LocalizationServiceInterface;
use DbCms\Core\Http\Controllers\Controller;
use DbCms\Core\Models\Market;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class GeoPreferencePostController extends Controller
{
    public function __invoke(Request $request, LocalizationServiceInterface $localization): RedirectResponse
    {
        $data = $request->validate(['market' => 'required|string', 'locale' => 'required|string']);
        $marketCode = $data['market'];
        $localeCode = $data['locale'];
        $market = Market::where('code', $marketCode)->where('is_active', true)->first();
        if ($market === null) {
            throw ValidationException::withMessages(['market' => 'Selecciona un mercado disponible.']);
        }
        $language = $market->languages()->where('code', $localeCode)->where('is_active', true)->first();
        if ($language === null) {
            throw ValidationException::withMessages(['locale' => 'Selecciona un idioma disponible para este mercado.']);
        }
        $targetUrl = $localization->url('/', $market, $language);

        return redirect($targetUrl)->cookie(
            'cms_geo_preference',
            "{$marketCode}_{$localeCode}",
            60 * 24 * 365,
            '/',
            null,
            $request->isSecure(),
            false,
            false,
            'lax'
        );
    }
}
