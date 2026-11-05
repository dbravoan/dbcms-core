<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Controllers\Cms;

use DbCms\Core\Contracts\Services\AdminThemeServiceInterface;
use DbCms\Core\Http\Controllers\Controller;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves static assets (CSS, logos, fonts) shipped inside a backoffice theme's
 * `assets/` directory without requiring a manual copy into `public/`.
 *
 * Only files under the theme's own assets directory are served; traversal and
 * unknown themes are rejected.
 */
final class ThemeAssetGetController extends Controller
{
    public function __invoke(string $theme, string $path, AdminThemeServiceInterface $themes): Response
    {
        $theme = strtolower($theme);

        if (! preg_match('/^[a-z0-9_-]+$/', $theme) || ! $themes->themeExists($theme)) {
            abort(404);
        }

        $path = str_replace('\\', '/', $path);
        if ($path === '' || str_contains($path, '..') || str_starts_with($path, '/')) {
            abort(404);
        }

        $assetsDir = $themes->getThemePath($theme).'/assets';
        $baseReal = realpath($assetsDir);
        $fileReal = realpath($assetsDir.'/'.$path);

        if ($baseReal === false || $fileReal === false || ! is_file($fileReal)) {
            abort(404);
        }

        // Ensure the resolved file stays inside the theme's assets directory.
        if (! str_starts_with($fileReal, $baseReal.DIRECTORY_SEPARATOR)) {
            abort(404);
        }

        /** @var BinaryFileResponse $response */
        $response = response()->file($fileReal, [
            'Content-Type' => $this->mimeType($fileReal),
            'Cache-Control' => 'public, max-age=86400',
        ]);

        return $response;
    }

    /**
     * Resolve the Content-Type for a theme asset by its extension.
     */
    private function mimeType(string $file): string
    {
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

        return match ($extension) {
            'css' => 'text/css; charset=UTF-8',
            'js' => 'application/javascript; charset=UTF-8',
            'svg' => 'image/svg+xml',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'ico' => 'image/x-icon',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            'otf' => 'font/otf',
            'json' => 'application/json',
            default => 'application/octet-stream',
        };
    }
}
