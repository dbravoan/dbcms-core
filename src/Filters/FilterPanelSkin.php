<?php

declare(strict_types=1);

namespace DbCms\Core\Filters;

use DbCms\Core\Services\Cms\FrontViewResolver;
use DbCms\Core\Services\Cms\ThemeManagerService;

/**
 * Resolves the visual skin of the filter panel for the active front theme.
 *
 * The panel markup is shared (one implementation, always accessible and always
 * canonical); the *look* is theme-owned so the widget never looks pasted on:
 *
 *   1. `themes/{theme}/filter.json` → class tokens merged over the neutral
 *      defaults (`config/front_filter_ui.php`);
 *   2. a theme may additionally override the whole structure with
 *      `themes/{theme}/filter/panel.blade.php`.
 *
 * Unknown themes (or themes shipped without a skin) simply get the neutral
 * defaults, so adding a theme never breaks the widget.
 */
final class FilterPanelSkin
{
    public function __construct(
        private readonly FrontViewResolver $views,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forTheme(?string $theme = null): array
    {
        $theme = $theme ?? $this->views->theme();

        /** @var array<string, mixed> $defaults */
        $defaults = config('front_filter_ui.default', []);

        $override = $this->readSkinFile($theme);

        if ($override === []) {
            return $defaults;
        }

        /** @var array<string, mixed> $skin */
        $skin = array_replace_recursive($defaults, $override);

        return $skin;
    }

    /**
     * Theme-provided token file, or [] when the theme has none.
     *
     * @return array<string, mixed>
     */
    private function readSkinFile(string $theme): array
    {
        $path = app(ThemeManagerService::class)->path($theme).'/filter.json';

        if (! is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded)) {
            return [];
        }

        // `tokens` keeps the file self-describing (name + tokens), but a flat
        // file is accepted too.
        $tokens = $decoded['tokens'] ?? $decoded;

        /** @var array<string, mixed> $tokens */
        return is_array($tokens) ? $tokens : [];
    }
}
