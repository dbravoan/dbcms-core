<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Cms;

use DbCms\Core\Contracts\Services\PublicContentDiscoveryInterface;
use DbCms\Core\Models\Market;
use DbCms\Core\Models\Menu;
use DbCms\Core\Models\MenuItem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class MenuService
{
    public function __construct(
        private readonly PageCacheService $pageCache
    ) {}

    /**
     * Resolves a hierarchical menu tree for the given location, market, and locale.
     *
     * Cached per (location, market, locale): menus are rendered on every page and
     * only change when the menu itself is edited (Menu/MenuItem observers bump the
     * page-cache version, which is part of the cache key).
     *
     * @return array<int, array{id: int, title: string, url: string, target_blank: bool, children: array<int, mixed>}>
     */
    public function getMenu(string $location, ?Market $market = null, ?string $locale = null): array
    {
        $locale = $locale ?? app()->getLocale();
        $marketCode = $market->code ?? 'es';

        $cacheKey = sprintf(
            'cms:menu:v%d:%s:%s:%s',
            $this->pageCache->version(),
            $location,
            $market->id ?? 0,
            $locale
        );

        try {
            return (array) Cache::remember($cacheKey, $this->pageCache->ttl(), function () use ($location, $market, $locale, $marketCode): array {
                return $this->buildMenu($location, $market, $locale, $marketCode);
            });
        } catch (\Throwable) {
            // Cache unavailable: render live.
            return $this->buildMenu($location, $market, $locale, $marketCode);
        }
    }

    /**
     * Builds the menu tree from the database.
     *
     * @return array<int, array{id: int, title: string, url: string, target_blank: bool, children: array<int, mixed>}>
     */
    private function buildMenu(string $location, ?Market $market, string $locale, string $marketCode): array
    {
        $menu = Menu::where('location', $location)
            ->where('is_active', true)
            ->where(function ($q) use ($market): void {
                if ($market) {
                    $q->whereHas('markets', function ($mq) use ($market): void {
                        $mq->where('markets.id', $market->id);
                    })->orDoesntHave('markets');
                }
            })
            ->with(['rootItems.translations', 'rootItems.children.translations'])
            ->first();

        if (! $menu) {
            return [];
        }

        $tree = [];
        foreach ($menu->rootItems as $item) {
            if (! $this->visibleInLocale($item, $locale)) {
                continue;
            }

            $formatted = $this->formatItem($item, $marketCode, $locale);
            if ($formatted !== null) {
                $tree[] = $formatted;
            }
        }

        return $tree;
    }

    /**
     * Formats a single menu item with localized title and URL.
     *
     * @return array{id: int, title: string, url: string, target_blank: bool, children: array<int, mixed>}|null
     */
    protected function formatItem(MenuItem $item, string $marketCode, string $locale): ?array
    {
        $translation = $item->translations->where('locale', $locale)->first()
            ?? $item->translations->first();

        $title = $translation ? $translation->title : "Item #{$item->id}";
        $urlOverride = $translation?->url_override;

        $url = $this->resolveItemUrl($item, $urlOverride, $marketCode, $locale);
        if ($url === null) {
            return null;
        }

        $children = [];
        foreach ($item->children as $child) {
            if ($this->visibleInLocale($child, $locale)) {
                $formatted = $this->formatItem($child, $marketCode, $locale);
                if ($formatted !== null) {
                    $children[] = $formatted;
                }
            }
        }

        return [
            'id' => $item->id,
            'title' => $title,
            'url' => $url,
            'target_blank' => (bool) $item->target_blank,
            'children' => $children,
        ];
    }

    private function visibleInLocale(MenuItem $item, string $locale): bool
    {
        return (bool) $item->is_active
            && ($item->type !== 'page' || $item->translations->contains('locale', $locale));
    }

    protected function resolveItemUrl(MenuItem $item, ?string $urlOverride, string $marketCode, string $locale): ?string
    {
        if ($item->type === 'page' && $item->target_id !== null) {
            $market = Market::where('code', $marketCode)->first();
            if ($market === null) {
                return null;
            }
            $discovery = app(PublicContentDiscoveryInterface::class);
            $page = $discovery->query('page', $market, $locale)->whereKey($item->target_id)->first();

            return $page === null ? null : $discovery->entityUrl('page', $page, $market, $locale);
        }
        $rawUrl = ! empty($urlOverride) ? $urlOverride : $item->url;

        if (! empty($rawUrl) && (str_starts_with($rawUrl, 'http') || str_starts_with($rawUrl, '#'))) {
            return $rawUrl;
        }

        return match ($item->type) {
            'blog' => url("/{$marketCode}/{$locale}/blog"),
            'shop' => url("/{$marketCode}/{$locale}/shop"),
            'gallery' => url("/{$marketCode}/{$locale}/".($locale === 'en' ? 'gallery' : 'galeria')),
            'page' => $this->resolvePageUrl($rawUrl, $marketCode, $locale),
            default => ! empty($rawUrl) ? url("/{$marketCode}/{$locale}/".ltrim($rawUrl, '/')) : url("/{$marketCode}/{$locale}"),
        };
    }

    protected function resolvePageUrl(?string $url, string $marketCode, string $locale): string
    {
        if (empty($url)) {
            return url("/{$marketCode}/{$locale}");
        }

        $cleanSlug = ltrim($url, '/');
        if (str_starts_with($cleanSlug, 'page/')) {
            $cleanSlug = substr($cleanSlug, 5);
        }
        $cleanSlug = trim($cleanSlug, '/');

        if ($cleanSlug === '' || $cleanSlug === 'home') {
            return url("/{$marketCode}/{$locale}");
        }

        return url("/{$marketCode}/{$locale}/{$cleanSlug}");
    }

    /**
     * Assigns menus to navigation zones in a database transaction.
     *
     * @param  array<string, int|string|null>  $zones
     */
    public function assignZones(array $zones): void
    {
        DB::transaction(function () use ($zones): void {
            foreach ($zones as $location => $menuId) {
                if (empty($location)) {
                    continue;
                }

                if (! empty($menuId) && $menuId !== 'none') {
                    Menu::where('location', $location)
                        ->where('id', '!=', (int) $menuId)
                        ->update(['location' => 'none']);

                    Menu::where('id', (int) $menuId)->update(['location' => $location]);
                } else {
                    Menu::where('location', $location)->update(['location' => 'none']);
                }
            }
        });
    }
}
