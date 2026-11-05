<?php

declare(strict_types=1);

namespace DbCms\Core\DTOs;

use DbCms\Core\Services\Cms\BrandService;

/**
 * Resolved brand identity for a backoffice theme.
 *
 * This is the only place consumers should read brand values from: the values
 * come from the active theme's `theme.json` (`brand` block), with optional
 * environment overrides resolved by {@see BrandService}.
 *
 * @see docs/18_THEME_MANIFEST_AND_BRAND_REFERENCE.md
 */
readonly class BrandIdentity extends BaseDTO
{
    public function __construct(
        public string $theme,
        public string $name,
        public string $tagline,
        public ?string $logo,
        public ?string $icon,
        public ?string $edition,
        public string $accent,
        public string $mode,
        public bool $valid,
    ) {}

    /**
     * @return array{
     *     theme: string,
     *     name: string,
     *     tagline: string,
     *     logo: string|null,
     *     icon: string|null,
     *     edition: string|null,
     *     accent: string,
     *     mode: string,
     *     valid: bool
     * }
     */
    public function toArray(): array
    {
        return [
            'theme' => $this->theme,
            'name' => $this->name,
            'tagline' => $this->tagline,
            'logo' => $this->logo,
            'icon' => $this->icon,
            'edition' => $this->edition,
            'accent' => $this->accent,
            'mode' => $this->mode,
            'valid' => $this->valid,
        ];
    }
}
