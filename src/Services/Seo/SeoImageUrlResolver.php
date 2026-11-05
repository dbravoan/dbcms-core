<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Seo;

/** Optional metadata images: declared media are preserved without remote probes. */
final class SeoImageUrlResolver
{
    public static function resolve(?string $configured, ?string $fallback = null): ?string
    {
        $configured = trim($configured ?? '');
        if ($configured !== '') {
            return self::absolute($configured);
        }
        $fallback = trim($fallback ?? '');
        if ($fallback === '') {
            return null;
        }
        $host = parse_url($fallback, PHP_URL_HOST);
        if ($host === null || $host === false || $host === parse_url((string) config('app.url'), PHP_URL_HOST) || $host === request()->getHost()) {
            $path = parse_url($fallback, PHP_URL_PATH);
            if (! is_string($path) || ! is_file(public_path(ltrim($path, '/')))) {
                return null;
            }
        }

        return self::absolute($fallback);
    }

    private static function absolute(string $value): ?string
    {
        if (str_starts_with($value, '//')) {
            return request()->getScheme().':'.$value;
        }
        $scheme = parse_url($value, PHP_URL_SCHEME);
        if ($scheme !== null && $scheme !== false && ! in_array(strtolower($scheme), ['http', 'https'], true)) {
            return null;
        }

        return url($value);
    }
}
