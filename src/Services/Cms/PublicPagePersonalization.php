<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Cms;

use Closure;
use Illuminate\Http\Request;

/** Stores public HTML only; private chrome and CSRF are rendered per request. */
final class PublicPagePersonalization
{
    private const TOKEN = '__CMS_REQUEST_CSRF__';

    public function __construct(private readonly ThemeManagerService $themes) {}

    /** @param array<string,mixed> $context */
    public function snapshot(string $html, string $csrfToken, array $context = []): string
    {
        $html = '<!--cms-public-context:'.base64_encode(json_encode($context, JSON_THROW_ON_ERROR)).'-->'.$html;
        $html = preg_replace_callback(
            '/<!--cms-private:([a-zA-Z0-9_.:-]+):start-->.*?<!--cms-private:end-->/s',
            static fn (array $match): string => '<!--cms-fragment:'.$match[1].'-->',
            $html,
        ) ?? $html;

        return $csrfToken === '' ? $html : str_replace($csrfToken, self::TOKEN, $html);
    }

    /** @param Closure(view-string): string $render */
    public function hydrate(string $html, string $csrfToken, Closure $render, ?Request $request = null): string
    {
        if (preg_match('/^<!--cms-public-context:([A-Za-z0-9+\/=]+)-->/D', $html, $context)) {
            $data = json_decode((string) base64_decode($context[1], true), true);
            if (is_array($data)) {
                foreach (['entity_type', 'entity_id', 'taxonomies'] as $key) {
                    $request?->attributes->set('cms.public.'.$key, $data[$key] ?? ($key === 'taxonomies' ? [] : null));
                }
            }
            $html = substr($html, strlen($context[0]));
        }
        $html = preg_replace_callback('/<!--cms-fragment:([a-zA-Z0-9_.:-]+)-->/s', function (array $match) use ($render): string {
            $view = $match[1];
            $header = preg_match('/^theme-([a-z0-9][a-z0-9-]*)::partials\.header$/D', $view, $theme) === 1
                && array_key_exists($theme[1], $this->themes->getAvailableThemes());
            $allowed = $header || in_array($view, ['themes::components.visitor-geo-modal', 'themes::components.cookie-consent-modal', 'themes::components.analytics', 'components.cms.overlays'], true);
            if (! $allowed) {
                throw new \UnexpectedValueException('Unregistered public cache fragment.');
            }

            /** @var view-string&non-empty-string $view */
            return $render($view);
        }, $html) ?? $html;

        return str_replace(self::TOKEN, $csrfToken, $html);
    }
}
