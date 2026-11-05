<?php

declare(strict_types=1);

namespace DbCms\Core\DTOs\Admin;

use DbCms\Core\DTOs\BaseDTO;
use Illuminate\Support\Facades\Route;

readonly class AdminNavigationItem extends BaseDTO
{
    /**
     * @param  array<int, AdminNavigationItem>  $children
     * @param  array<string, mixed>  $routeParameters
     */
    public function __construct(
        public string $id,
        public string $label,
        public ?string $route,
        public string $icon,
        public ?string $createRoute = null,
        public ?string $createTooltip = null,
        public ?string $badgeText = null,
        public ?string $badgeClass = null,
        public bool $active = false,
        public ?string $module = null,
        public array $children = [],
        public bool $visible = true,
        public array $routeParameters = [],
        public ?string $url = null
    ) {}

    public function getUrl(): ?string
    {
        if ($this->url !== null) {
            return $this->url;
        }

        if ($this->route !== null && Route::has($this->route)) {
            return route($this->route, $this->routeParameters);
        }

        return null;
    }
}
