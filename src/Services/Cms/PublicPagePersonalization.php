<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Cms;

use Closure;

/** Stores public HTML only; private chrome and CSRF are rendered per request. */
final class PublicPagePersonalization
{
    private const TOKEN = '__CMS_REQUEST_CSRF__';

    public function __construct(private readonly ThemeManagerService $themes) {}

    public function snapshot(string $html, string $csrfToken): string
    {
        $html = preg_replace_callback(
            '/<!--cms-private:([a-zA-Z0-9_.:-]+):start-->.*?<!--cms-private:end-->/s',
            static fn (array $match): string => '<!--cms-fragment:'.$match[1].'-->',
            $html,
        ) ?? $html;

        return $csrfToken === '' ? $html : str_replace($csrfToken, self::TOKEN, $html);
    }

    /** @param Closure(view-string): string $render */
    public function hydrate(string $html, string $csrfToken, Closure $render): string
    {
        $html = preg_replace_callback('/<!--cms-fragment:([a-zA-Z0-9_.:-]+)-->/s', function (array $match) use ($render): string {
            $view = $match[1];
            $header = preg_match('/^theme-([a-z0-9][a-z0-9-]*)::partials\.header$/D', $view, $theme) === 1
                && array_key_exists($theme[1], $this->themes->getAvailableThemes());
            $allowed = $header || in_array($view, ['themes::components.visitor-geo-modal', 'themes::components.cookie-consent-modal', 'themes::components.analytics'], true);
            if (! $allowed) {
                throw new \UnexpectedValueException('Unregistered public cache fragment.');
            }

            /** @var view-string&non-empty-string $view */
            return $render($view);
        }, $html) ?? $html;

        return str_replace(self::TOKEN, $csrfToken, $html);
    }
}
