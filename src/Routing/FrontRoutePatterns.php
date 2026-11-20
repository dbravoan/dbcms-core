<?php

declare(strict_types=1);

namespace DbCms\Core\Routing;

use DbCms\Core\Models\Language;
use Throwable;

/** A content slug or module prefix must never be consumed as a locale. */
final class FrontRoutePatterns
{
    public static function locale(): string
    {
        try {
            $codes = Language::query()->pluck('code')->filter(fn ($code): bool => is_string($code) && $code !== '')->all();
            if ($codes !== []) {
                return '(?:[a-zA-Z]{2}(?:[-_][a-zA-Z]{2,4})?|'.implode('|', array_map(fn (string $code): string => preg_quote($code, '/'), $codes)).')';
            }
        } catch (Throwable) {
            // Routes must also register before installation creates the language table.
        }

        return '[a-zA-Z]{2}(?:[-_][a-zA-Z]{2,4})?';
    }
}
