<?php

declare(strict_types=1);

namespace DbCms\Core\Blocks\Components\Services;

use DbCms\Core\Models\BlockComponent;
use DbCms\Core\Models\Market;
use DbCms\Core\Sections\SectionLayout;
use DbCms\Core\Services\Cms\CacheDependencyCollector;
use DbCms\Core\Services\Content\PublicTranslation;
use Illuminate\Database\Eloquent\Builder;

final class PublicComponentReader
{
    /** @return Builder<BlockComponent> */
    public function query(Market $market, string $locale): Builder
    {
        app(CacheDependencyCollector::class)->recordType('block_components');
        $query = BlockComponent::query()->published()->forMarket($market)->with(['translations' => fn ($q) => $q->where('locale', $locale)->where(fn ($row) => $row->whereNull('market_id')->orWhere('market_id', $market->id))]);
        if (! $market->is_active || ! $market->languages()->where('code', $locale)->where('is_active', true)->exists()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('translations', fn ($q) => $q->where('locale', $locale)->where(fn ($row) => $row->whereNull('market_id')->orWhere('market_id', $market->id)));
    }

    public function find(int $id, Market $market, string $locale): ?BlockComponent
    {
        if ($id < 1 || ! $market->is_active || ! $market->languages()->where('code', $locale)->where('is_active', true)->exists()) {
            return null;
        }

        return $this->query($market, $locale)->whereKey($id)->with(['sections' => fn ($q) => $q->where('is_active', true)->where('status', 'published')->with('translations')])->first();
    }

    /** @return list<array<string,mixed>> */
    public function sections(BlockComponent $component, Market $market, string $locale): array
    {
        $rows = [];
        foreach ($component->sections as $section) {
            if (! $section->is_active || $section->status !== 'published') {
                continue;
            }
            $translation = PublicTranslation::resolve($section, $locale, $market);
            if ($translation !== null) {
                $rows[] = SectionLayout::resolve((array) ($section->getAttribute('styling_json') ?? []), (array) $translation->getAttribute('content_json'));
            }
        }

        return $rows;
    }
}
