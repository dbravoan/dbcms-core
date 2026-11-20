<?php

declare(strict_types=1);

namespace DbCms\Core\Content\Pages\Services;

use DbCms\Core\Content\Pages\Models\Page;
use DbCms\Core\Contracts\Services\PublicContentDiscoveryInterface;
use DbCms\Core\Models\Language;
use DbCms\Core\Models\Market;
use Illuminate\Support\Collection;

/** Resolves administrative live links through the same visibility and URL contract as public discovery. */
final class PageAdminPublicLinks
{
    public function __construct(private readonly PublicContentDiscoveryInterface $discovery) {}

    /**
     * @param  Collection<int, Page>  $pages
     * @return array<int, string>
     */
    public function forPages(Collection $pages): array
    {
        $remaining = $pages->keyBy(fn (Page $page): int => $page->id);
        $links = [];
        // Preference only orders eligible contexts; it never bypasses page market assignment.
        $markets = $this->discovery->markets()->sortBy([
            fn (Market $a, Market $b): int => (int) $b->is_default <=> (int) $a->is_default,
            fn (Market $a, Market $b): int => $a->id <=> $b->id,
        ]);
        foreach ($markets as $market) {
            $languages = $market->languages->sortBy([
                fn (Language $a, Language $b): int => (int) ($b->code === $market->default_locale) <=> (int) ($a->code === $market->default_locale),
                fn (Language $a, Language $b): int => $a->id <=> $b->id,
            ]);
            foreach ($languages as $language) {
                if ($remaining->isEmpty()) {
                    return $links;
                }
                // Batch the current listing instead of issuing one visibility query per page.
                $visibleIds = $this->discovery->query('page', $market, $language->code)
                    ->whereKey($remaining->keys()->all())->pluck('pages.id');
                foreach ($visibleIds as $id) {
                    $id = (int) $id;
                    $page = $remaining->get($id);
                    if ($page === null) {
                        continue;
                    }
                    $links[$id] = $this->discovery->entityUrl('page', $page, $market, $language->code);
                    $remaining->forget($id);
                }
            }
        }

        return $links;
    }
}
