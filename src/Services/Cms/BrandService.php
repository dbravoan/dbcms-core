<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Cms;

use DbCms\Core\Contracts\Services\AdminThemeServiceInterface;
use DbCms\Core\Contracts\Services\BrandServiceInterface;
use DbCms\Core\DTOs\BrandIdentity;

/**
 * Resolves the brand identity for backoffice themes.
 *
 * Resolution order per value: environment override → theme manifest
 * (`theme.json` → `brand`) → generic default. A configured theme that is not
 * installed fails explicitly; there is no implicit fallback between themes.
 *
 * @see docs/EXTENSIONS.md
 */
final class BrandService implements BrandServiceInterface
{
    /**
     * @var array<string, BrandIdentity>
     */
    private array $cache = [];

    /**
     * Last `cms.admin_theme` value seen, to detect runtime switches.
     */
    private ?string $lastConfiguredTheme = null;

    public function __construct(
        private readonly AdminThemeServiceInterface $themes,
    ) {}

    public function activeTheme(): string
    {
        $requested = strtolower(trim((string) config('cms.admin_theme', 'dbcms')));

        // Long-lived processes (queue workers, tests) may switch the theme at
        // runtime; re-point the view finder when that happens.
        if ($this->lastConfiguredTheme !== $requested) {
            $this->lastConfiguredTheme = $requested;
            $this->themes->resetActiveTheme();
            $this->themes->setActiveTheme($requested);
            $this->cache = [];
        }

        if ($requested === '' || ! $this->themes->themeExists($requested)) {
            throw new \DomainException("The selected admin theme [{$requested}] is not installed.");
        }

        return $requested;
    }

    public function current(): BrandIdentity
    {
        return $this->forTheme($this->activeTheme());
    }

    public function forTheme(string $theme): BrandIdentity
    {
        $theme = strtolower(trim($theme));

        if (isset($this->cache[$theme])) {
            return $this->cache[$theme];
        }

        if ($theme === '') {
            return $this->cache[$theme] = $this->neutral();
        }

        $manifest = $this->themes->getThemeManifest($theme);
        $brand = $manifest['brand'];

        $name = trim($brand['name']);
        if ($name === '') {
            $name = (string) config('cms.name', 'CMS');
        }

        // `CMS_TAGLINE` overrides the manifest tagline when explicitly set.
        $envTagline = config('cms.tagline');
        $tagline = is_string($envTagline) && trim($envTagline) !== ''
            ? trim($envTagline)
            : $brand['tagline'];

        return $this->cache[$theme] = new BrandIdentity(
            theme: $theme,
            name: $name,
            tagline: $tagline,
            logo: $brand['logo'],
            icon: $brand['icon'],
            edition: $brand['edition'],
            accent: $brand['accent'],
            mode: $brand['mode'],
            valid: $this->themes->themeExists($theme),
        );
    }

    /**
     * Neutral identity used when no backoffice theme resolves to a directory.
     */
    private function neutral(): BrandIdentity
    {
        return new BrandIdentity(
            theme: '',
            name: (string) config('cms.name', 'CMS'),
            tagline: (string) config('cms.tagline'),
            logo: null,
            icon: null,
            edition: null,
            accent: '#38BDF8',
            mode: 'dark',
            valid: false,
        );
    }
}
