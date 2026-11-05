<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Cms;

/**
 * Resolves the localized front-office chrome strings (footer headings, legal
 * links, labels, …) for the active market locale.
 *
 * The copy lives in `lang/{locale}/front.php`, so adding a language means adding
 * a translation file — never touching this class or any map.
 */
class FrontChromeTranslator
{
    /**
     * Chrome keys resolved from `front.chrome`.
     *
     * @var list<string>
     */
    private const KEYS = [
        'brand_description',
        'quick_nav_title',
        'legal_title',
        'home_title',
        'blog_title',
        'about_title',
        'services_title',
        'contact_title',
        'privacy_title',
        'terms_title',
        'cookie_settings_title',
        'rights_reserved',
        'market_active_label',
        'language_label',
    ];

    /**
     * Resolve all chrome strings for a locale (Laravel falls back to the app
     * fallback locale for any missing key).
     *
     * @return array<string, string>
     */
    public function for(string $locale): array
    {
        $previous = app()->getLocale();

        try {
            // Keep the code as-is: language directories are case-sensitive
            // (`lang/zh-CN`), while comparison helpers lower-case only when
            // they need to be lenient.
            app()->setLocale($locale);

            $resolved = [];
            foreach (self::KEYS as $key) {
                $resolved[$this->camel($key)] = (string) __('front.chrome.'.$key);
            }

            return $resolved;
        } finally {
            app()->setLocale($previous);
        }
    }

    /**
     * `quick_nav_title` → `quickNavTitle`, matching the theme's variable names.
     */
    private function camel(string $key): string
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $key))));
    }
}
