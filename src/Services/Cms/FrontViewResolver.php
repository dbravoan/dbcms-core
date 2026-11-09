<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Cms;

use Illuminate\Contracts\View\Factory;

/**
 * Single source of truth for front-office theme view resolution.
 *
 * Every front controller, widget and block used to repeat the same
 * `view()->exists("themes::{theme}.…") ? … : fallback` dance. Centralising it
 * keeps the theme override rules consistent and makes the active theme an
 * explicit dependency instead of a `config('cms.theme')` call spread around.
 */
final class FrontViewResolver
{
    /**
     * Theme used when no valid theme is configured.
     */
    public const DEFAULT_THEME = 'db-cyber';

    public function __construct(
        private readonly Factory $views,
        private readonly ThemeManagerService $themes,
    ) {}

    /**
     * Active front-office theme.
     */
    public function theme(): string
    {
        return $this->themes->getActiveTheme();
    }

    /**
     * Fully qualified theme view name, whether or not it exists.
     */
    /** @return view-string */
    public function themeView(string $view): string
    {
        /** @var view-string&non-falsy-string $resolved */
        $resolved = 'theme-'.$this->theme().'::'.$view;

        return $resolved;
    }

    /**
     * Resolve a theme view, falling back to a module namespace view.
     */
    /**
     * @param  view-string  $fallback
     * @return view-string
     */
    public function resolve(string $view, string $fallback): string
    {
        $candidate = $this->themeView($view);

        return $this->views->exists($candidate) ? $candidate : $fallback;
    }

    /**
     * Resolve the theme layout. The selected theme must supply it.
     */
    /** @return view-string */
    public function layout(string $layout = 'app'): string
    {
        $view = $this->themeView("layouts.{$layout}");
        if (! $this->views->exists($view)) {
            throw new \DomainException('The selected theme must supply its own layout.');
        }

        return $view;
    }

    /**
     * Resolve a block view: theme override first, then the base view.
     *
     * Returns null when neither exists so the caller can decide how to fail.
     */
    /** @return view-string|null */
    public function block(string $view): ?string
    {
        $themed = $this->themeView($view);

        if ($this->views->exists($themed)) {
            return $themed;
        }

        return $this->views->exists($view) ? $view : null;
    }

    /**
     * Resolve the filter panel: a theme may replace the whole structure with
     * `themes/{theme}/filter/panel.blade.php`, otherwise the shared
     * implementation renders it with the theme's skin tokens.
     */
    /** @return view-string */
    public function filterPanel(): string
    {
        foreach ([
            $this->themeView('filter.panel'),
            $this->themeView('components.filter-panel'),
        ] as $candidate) {
            if ($this->views->exists($candidate)) {
                return $candidate;
            }
        }

        return 'components.cms-filter.panel';
    }
}
