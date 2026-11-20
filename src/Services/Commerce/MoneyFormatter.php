<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Commerce;

use NumberFormatter;

/**
 * One CLDR-aware money formatter for the backoffice and the storefront.
 *
 * Currency codes are ISO 4217; the market owns the code, the active locale
 * owns the symbol placement and separators.
 */
final class MoneyFormatter
{
    public function format(float $amount, ?string $currency = null, ?string $locale = null): string
    {
        $currency = strtoupper(trim((string) ($currency ?? 'EUR')));
        $locale = $locale ?? app()->getLocale();

        if (class_exists(NumberFormatter::class)) {
            $formatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);
            $formatted = $formatter->formatCurrency($amount, $currency);
            if (is_string($formatted) && $formatted !== '') {
                // CLDR may use a non-breaking space; keep the existing public
                // markup and test contract with a regular space.
                return str_replace(["\u{00A0}", "\u{202F}"], ' ', $formatted);
            }
        }

        return number_format($amount, 2, ',', '.').' '.$currency;
    }
}
