<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Admin;

use DbCms\Core\Content\Contracts\EditorialWorkflowInterface;
use DbCms\Core\Contracts\Services\AdminNavigationServiceInterface;
use DbCms\Core\DTOs\Admin\AdminNavigationItem;
use DbCms\Core\DTOs\Admin\AdminNavigationSection;
use DbCms\Core\Models\User;
use DbCms\Core\Modules\Contracts\MaintenanceModeInterface;
use DbCms\Core\Modules\ModuleManager;
use Illuminate\Routing\Exceptions\UrlGenerationException;
use Illuminate\Support\Facades\Route;

class AdminNavigationService implements AdminNavigationServiceInterface
{
    public function __construct(
        protected ModuleManager $moduleManager
    ) {}

    /**
     * {@inheritDoc}
     */
    public function getActiveModuleIdentifiers(): array
    {
        return array_keys($this->moduleManager->getActiveModules());
    }

    /**
     * {@inheritDoc}
     */
    public function getPendingAuditCount(): int
    {
        try {
            $actor = auth()->user();
            if ($actor instanceof User) {
                return app(EditorialWorkflowInterface::class)->pendingReviewCount($actor);
            }
        } catch (\Throwable) {
            // Table or model issue
        }

        return 0;
    }

    /**
     * {@inheritDoc}
     */
    public function isMaintenanceActive(): bool
    {
        try {
            return app(MaintenanceModeInterface::class)->isActive();
        } catch (\Throwable) {
            // Maintenance service unavailable
        }

        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function getNavigationSections(?User $user = null): array
    {
        /** @var User|null $user */
        $user = $user ?? auth()->user();

        $context = $this->permissionContext($user);

        return array_values(array_filter([
            $this->generalSection($context),
            $this->contentSection($context),
            $this->designSection($context),
            $this->extensionsSection($context),
            $this->systemSection($context),
        ]));
    }

    /**
     * Resolve the permission flags shared by every navigation section.
     *
     * @return array{isAdmin: bool, isViewer: bool, isEditor: bool, isAuthor: bool, isMarketing: bool, can: callable(string): bool}
     */
    private function permissionContext(?User $user): array
    {
        return [
            'isAdmin' => $user?->isAdmin() ?? false,
            'isViewer' => $user?->hasRole('viewer') ?? false,
            'isEditor' => $user?->hasRole('editor') ?? false,
            'isAuthor' => $user?->hasRole('author') ?? false,
            'isMarketing' => $user?->hasRole('marketing') ?? false,
            'can' => fn (string $ability): bool => $user !== null && $user->can($ability),
        ];
    }

    /**
     * @param  array{isAdmin: bool, isViewer: bool, isEditor: bool, isAuthor: bool, isMarketing: bool, can: callable(string): bool}  $ctx
     */
    private function generalSection(array $ctx): AdminNavigationSection
    {
        $isAdmin = $ctx['isAdmin'];
        $isEditor = $ctx['isEditor'];
        $isMarketing = $ctx['isMarketing'];
        $isViewer = $ctx['isViewer'];
        $can = $ctx['can'];

        $items = [];
        $items[] = new AdminNavigationItem(
            id: 'nav-dashboard',
            label: __('admin.nav.items.dashboard'),
            route: 'admin.dashboard',
            icon: 'fa-solid fa-chart-line',
            active: request()->routeIs('admin.dashboard')
        );

        if ($isAdmin || $isEditor || $isMarketing || $isViewer || $can('manage:seo')) {
            $items[] = new AdminNavigationItem(
                id: 'nav-seo',
                label: __('admin.nav.items.seo'),
                route: 'admin.seo.audit',
                icon: 'fa-solid fa-robot',
                active: request()->routeIs('admin.seo.audit')
            );

            $items[] = new AdminNavigationItem(
                id: 'nav-seo-filters',
                label: __('admin.nav.items.filters'),
                route: 'admin.seo.filters.index',
                icon: 'fa-solid fa-filter',
                active: request()->routeIs('admin.seo.filters.*')
            );
        }

        return new AdminNavigationSection(__('admin.nav.sections.general'), $items, true);
    }

    /**
     * @param  array{isAdmin: bool, isViewer: bool, isEditor: bool, isAuthor: bool, isMarketing: bool, can: callable(string): bool}  $ctx
     */
    private function contentSection(array $ctx): ?AdminNavigationSection
    {
        $isAdmin = $ctx['isAdmin'];
        $isEditor = $ctx['isEditor'];
        $isAuthor = $ctx['isAuthor'];
        $isViewer = $ctx['isViewer'];
        $can = $ctx['can'];

        $items = [];

        // Pages
        if (($isAdmin || $isEditor || $isViewer || $can('manage:pages'))) {
            $items[] = new AdminNavigationItem(
                id: 'nav-pages',
                label: __('admin.nav.items.pages'),
                route: 'admin.pages.index',
                icon: 'fa-solid fa-file-lines',
                createRoute: ($isAdmin || $isEditor || $can('manage:pages')) ? 'admin.pages.create' : null,
                createTooltip: __('admin.nav.create.page'),
                active: request()->routeIs('admin.pages.*')
            );
        }

        return empty($items) ? null : new AdminNavigationSection(__('admin.nav.sections.content'), $items, true);
    }

    /**
     * @param  array{isAdmin: bool, isViewer: bool, isEditor: bool, isAuthor: bool, isMarketing: bool, can: callable(string): bool}  $ctx
     */
    private function designSection(array $ctx): ?AdminNavigationSection
    {
        $isAdmin = $ctx['isAdmin'];
        $isEditor = $ctx['isEditor'];
        $isViewer = $ctx['isViewer'];
        $can = $ctx['can'];

        if (! ($isAdmin || $isEditor || $isViewer || $can('manage:menus') || $can('manage:themes') || $can('manage:media'))) {
            return null;
        }

        $items = [];

        if ($isAdmin || $isEditor || $isViewer || $can('manage:menus')) {
            $items[] = new AdminNavigationItem(
                id: 'nav-menus',
                label: __('admin.nav.items.menus'),
                route: 'admin.menus.index',
                icon: 'fa-solid fa-compass',
                active: request()->routeIs('admin.menus.*')
            );
        }

        if ($isAdmin || $isEditor || $isViewer || $can('manage:themes')) {
            $items[] = new AdminNavigationItem(
                id: 'nav-themes',
                label: __('admin.nav.items.themes'),
                route: 'admin.themes.index',
                icon: 'fa-solid fa-palette',
                active: request()->routeIs('admin.themes.*')
            );
        }

        return empty($items) ? null : new AdminNavigationSection(__('admin.nav.sections.design'), $items, true);
    }

    /**
     * @param  array{isAdmin: bool, isViewer: bool, isEditor: bool, isAuthor: bool, isMarketing: bool, can: callable(string): bool}  $ctx
     */
    private function extensionsSection(array $ctx): ?AdminNavigationSection
    {
        $isAdmin = $ctx['isAdmin'];
        $can = $ctx['can'];

        $items = [];
        foreach ($this->moduleManager->getActiveModules() as $identifier => $module) {
            foreach ($module->getAdminNavigation() as $item) {
                $navigation = $this->moduleNavigationItem($identifier, $item, $ctx);
                if ($navigation !== null) {
                    $items[] = $navigation;
                }
            }
        }

        return empty($items) ? null : new AdminNavigationSection(__('admin.nav.sections.extensions'), $items, true);
    }

    /**
     * @param  array{isAdmin: bool, isViewer: bool, isEditor: bool, isAuthor: bool, isMarketing: bool, can: callable(string): bool}  $ctx
     */
    private function systemSection(array $ctx): ?AdminNavigationSection
    {
        $isAdmin = $ctx['isAdmin'];
        $isViewer = $ctx['isViewer'];
        $can = $ctx['can'];

        if (! ($isAdmin || $isViewer || $can('manage:modules') || $can('manage:users') || $can('manage:storage') || $can('manage:blocks'))) {
            return null;
        }

        $items = [];

        if ($isAdmin || $isViewer || $can('manage:modules')) {
            $items[] = new AdminNavigationItem(
                id: 'nav-settings',
                label: __('admin.nav.items.site_settings'),
                route: 'admin.settings.index',
                icon: 'fa-solid fa-sliders',
                active: request()->routeIs('admin.settings.*')
            );

            $items[] = new AdminNavigationItem(
                id: 'nav-modules',
                label: __('admin.nav.items.modules'),
                route: 'admin.modules.index',
                icon: 'fa-solid fa-puzzle-piece',
                active: request()->routeIs('admin.modules.index')
            );

            $items[] = new AdminNavigationItem(
                id: 'nav-markets',
                label: __('admin.nav.items.markets'),
                route: 'admin.markets.index',
                icon: 'fa-solid fa-globe',
                active: request()->routeIs('admin.markets.*')
            );
        }

        if ($isAdmin || $can('manage:blocks')) {
            $items[] = new AdminNavigationItem(
                id: 'nav-block-packages', label: 'Paquetes de bloques',
                route: 'admin.block-packages.index', icon: 'fa-solid fa-cubes',
                active: request()->routeIs('admin.block-packages.*')
            );
        }

        if ($isAdmin || $can('manage:storage')) {
            $items[] = new AdminNavigationItem(
                id: 'nav-connections',
                label: __('admin.nav.items.connections'),
                route: 'admin.connections.index',
                icon: 'fa-solid fa-cloud-arrow-up',
                active: request()->routeIs('admin.connections.*')
            );
        }

        if ($isAdmin || $isViewer || $can('manage:users')) {
            $items[] = new AdminNavigationItem(
                id: 'nav-users',
                label: __('admin.nav.items.users'),
                route: 'admin.users.index',
                icon: 'fa-solid fa-users',
                active: request()->routeIs('admin.users.*')
            );

            $items[] = new AdminNavigationItem(
                id: 'nav-tokens',
                label: __('admin.nav.items.tokens'),
                route: 'admin.tokens.index',
                icon: 'fa-solid fa-key',
                active: request()->routeIs('admin.tokens.*')
            );
        }

        return empty($items) ? null : new AdminNavigationSection(__('admin.nav.sections.system'), $items, true);
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, mixed>  $context
     */
    private function moduleNavigationItem(string $identifier, array $item, array $context): ?AdminNavigationItem
    {
        if (($item['visible'] ?? true) === false || (! empty($item['permission']) && ! ($context['isAdmin'] || $context['can']($item['permission'])))) {
            return null;
        }

        $children = [];
        foreach ($item['children'] ?? [] as $child) {
            $navigation = $this->moduleNavigationItem($identifier, $child, $context);
            if ($navigation !== null) {
                $children[] = $navigation;
            }
        }

        $icon = $item['icon'] ?? 'puzzle-piece';
        $icon = str_contains($icon, ' ') ? $icon : 'fa-solid fa-'.$icon;
        $identity = $item['id'] ?? $item['route'] ?? 'group';
        $id = 'nav-'.$identifier.'-'.str_replace(['.', '_'], '-', $identity);
        $parameters = $item['params'] ?? [];
        if ($parameters !== []) {
            $id .= '-'.substr(hash('sha256', json_encode($parameters, JSON_THROW_ON_ERROR)), 0, 10);
        }

        if (array_key_exists('children', $item)) {
            if ($children === []) {
                return null;
            }
            if (count($children) === 1) {
                return $children[0];
            }

            return new AdminNavigationItem(
                id: $id, label: $item['title'], route: null, icon: $icon,
                badgeText: $item['badgeText'] ?? $item['badge'] ?? null,
                badgeClass: $item['badgeClass'] ?? null,
                active: count(array_filter($children, fn (AdminNavigationItem $child): bool => $child->active)) > 0,
                module: $identifier, children: $children
            );
        }

        $route = $item['route'] ?? null;
        if ($route === null || ! Route::has($route)) {
            return null;
        }
        try {
            route($route, $parameters);
        } catch (UrlGenerationException) {
            return null;
        }

        $active = false;
        if ($parameters !== []) {
            $active = request()->routeIs($route);
            foreach ($parameters as $key => $value) {
                $active = $active && (string) (request()->route($key) ?? request()->query($key)) === (string) $value;
            }
        } else {
            $pattern = $item['activePattern'] ?? (str_ends_with($route, '.index') ? substr($route, 0, -6).'.*' : $route);
            $active = $this->routeIsActive($pattern);
        }

        return new AdminNavigationItem(
            id: $id,
            label: $item['title'], route: $route,
            icon: $icon,
            createRoute: $item['createRoute'] ?? null,
            createTooltip: $item['createTooltip'] ?? null,
            badgeText: $item['badgeText'] ?? $item['badge'] ?? null,
            badgeClass: $item['badgeClass'] ?? null,
            active: $active, module: $identifier, children: $children,
            routeParameters: $parameters, url: $item['url'] ?? null
        );
    }

    /**
     * Whether a route name (or its wildcard) is the current request.
     *
     * @param  string|array<int, string>  $route
     */
    private function routeIsActive(string|array $route): bool
    {
        try {
            $patterns = is_array($route) ? $route : [$route, $route.'.*'];

            return request()->routeIs(...$patterns);
        } catch (\Throwable) {
            return false;
        }
    }
}
