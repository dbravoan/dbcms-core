<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Install;

use DbCms\Core\Contracts\Services\AdminThemeServiceInterface;
use DbCms\Core\Services\Cms\ThemeManagerService;
use Illuminate\Support\Facades\File;

/**
 * Discovers the installable CMS brands and front-office themes available on
 * disk. Deliberately filesystem-only (no database access) because the web
 * installer runs before the schema exists.
 */
final class BrandRegistry
{
    public const NEUTRAL_IDENTIFIER = 'neutral';

    /**
     * @return array<int, array{
     *     identifier: string,
     *     name: string,
     *     theme_name: string,
     *     description: string,
     *     logo: string|null,
     *     icon: string|null,
     *     edition: string|null,
     *     accent: string,
     *     mode: string
     * }>
     */
    public function brands(): array
    {
        /** @var AdminThemeServiceInterface $themes */
        $themes = app(AdminThemeServiceInterface::class);
        $brands = [];

        foreach ($themes->getAvailableThemes() as $identifier => $manifest) {
            /** @var array<string, mixed> $brand */
            $brand = is_array($manifest['brand'] ?? null) ? $manifest['brand'] : [];

            $brands[] = [
                'identifier' => (string) $identifier,
                'name' => trim($brand['name']) !== '' ? $brand['name'] : (string) $manifest['name'],
                'theme_name' => (string) $manifest['name'],
                'description' => (string) $manifest['description'],
                'logo' => $this->assetUrl((string) $identifier, $brand['logo']),
                'icon' => $this->assetUrl((string) $identifier, $brand['icon']),
                'edition' => $brand['edition'],
                'accent' => $brand['accent'],
                'mode' => $brand['mode'],
            ];
        }

        usort($brands, static fn (array $a, array $b): int => strcmp($a['identifier'], $b['identifier']));

        return $brands;
    }

    /**
     * @return array{
     *     identifier: string,
     *     name: string,
     *     theme_name: string,
     *     description: string,
     *     logo: string|null,
     *     icon: string|null,
     *     edition: string|null,
     *     accent: string,
     *     mode: string
     * }|null
     */
    public function find(string $identifier): ?array
    {
        $identifier = strtolower(trim($identifier));

        if ($identifier === self::NEUTRAL_IDENTIFIER) {
            return $this->neutral();
        }

        foreach ($this->brands() as $brand) {
            if ($brand['identifier'] === $identifier) {
                return $brand;
            }
        }

        return null;
    }

    /**
     * @return array{
     *     identifier: string,
     *     name: string,
     *     theme_name: string,
     *     description: string,
     *     logo: string|null,
     *     icon: string|null,
     *     edition: string|null,
     *     accent: string,
     *     mode: string
     * }
     */
    public function neutral(): array
    {
        return [
            'identifier' => self::NEUTRAL_IDENTIFIER,
            'name' => 'Neutral',
            'theme_name' => 'Neutral installer',
            'description' => 'Identidad neutra del instalador, sin marca de producto.',
            'logo' => null,
            'icon' => null,
            'edition' => null,
            'accent' => '#38BDF8',
            'mode' => 'dark',
        ];
    }

    /**
     * Front-office themes discovered under themes/{id}/theme.json.
     *
     * @return array<int, array{identifier: string, name: string, description: string}>
     */
    public function frontThemes(): array
    {
        $themes = [];
        foreach (app(ThemeManagerService::class)->getAvailableThemes() as $identifier => $manifest) {
            $themes[] = [
                'identifier' => $identifier,
                'name' => trim((string) $manifest['name']),
                'description' => trim((string) ($manifest['description'] ?? '')),
            ];
        }

        usort($themes, static fn (array $a, array $b): int => strcmp($a['identifier'], $b['identifier']));

        return $themes;
    }

    /**
     * @return array<int, string>
     */
    public function brandIdentifiers(): array
    {
        return array_map(
            static fn (array $brand): string => $brand['identifier'],
            $this->brands()
        );
    }

    /**
     * @return array<int, string>
     */
    public function frontThemeIdentifiers(): array
    {
        return array_map(
            static fn (array $theme): string => $theme['identifier'],
            $this->frontThemes()
        );
    }

    private function assetUrl(string $theme, mixed $file): ?string
    {
        if (! is_string($file) || trim($file) === '') {
            return null;
        }

        $file = ltrim(trim($file), '/');

        if (! File::exists(app(AdminThemeServiceInterface::class)->getThemePath($theme)."/assets/{$file}")) {
            return null;
        }

        return theme_asset($file, $theme);
    }
}
