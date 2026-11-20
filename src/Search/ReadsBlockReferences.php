<?php

declare(strict_types=1);

namespace DbCms\Core\Search;

use DbCms\Core\Blocks\BlockTree;
use DbCms\Core\Contracts\Sectionable;
use DbCms\Core\Contracts\SectionContentVisibility;
use DbCms\Core\Contracts\Services\PublicContentDiscoveryInterface;
use DbCms\Core\Sections\SectionLayout;
use DbCms\Core\Services\Content\PublicTranslation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Uses the owner's index query, preserving scheduled rows without reading private drafts. */
trait ReadsBlockReferences
{
    /** @return Builder<covariant Model> */
    abstract protected function query(): Builder;

    /** @param list<int|string> $values
     * @return iterable<int,int|string> */
    public function referencingEntities(string $blockType, string $field, array $values): iterable
    {
        $values = array_fill_keys(array_map(strval(...), $values), true);
        if ($values === []) {
            return;
        }
        $markets = app(PublicContentDiscoveryInterface::class)->markets();
        $tree = app(BlockTree::class);
        foreach ($this->query()->lazyById(100) as $entity) {
            if (! $entity instanceof Sectionable || ! method_exists($entity, 'publishedSections') || ($entity instanceof SectionContentVisibility && ! $entity->usesSectionContent())) {
                continue;
            }
            $entity->loadMissing('translations', 'publishedSections.translations');
            $memberships = method_exists($entity, 'markets') ? $entity->loadMissing('markets')->getRelation('markets') : collect();
            foreach ($markets as $market) {
                if ($memberships->isNotEmpty() && ! $memberships->contains('id', $market->getKey())) {
                    continue;
                }
                foreach ($market->languages->where('is_active', true) as $language) {
                    $locale = (string) $language->getAttribute('code');
                    if (! $entity->getRelation('translations')->contains('locale', $locale)) {
                        continue;
                    }
                    foreach ($entity->getRelation('publishedSections') as $section) {
                        $translation = PublicTranslation::resolve($section, $locale, $market);
                        if ($translation === null) {
                            continue;
                        }
                        $payload = SectionLayout::resolve(array_replace(['type' => $section->getAttribute('type')], (array) $section->getAttribute('styling_json')), (array) $translation->getAttribute('content_json'));
                        foreach ($tree->nodes($payload) as $node) {
                            $reference = $node[$field] ?? null;
                            if (($node['type'] ?? '') === $blockType && (is_int($reference) || is_string($reference)) && isset($values[(string) $reference])) {
                                $key = $entity->getKey();
                                if (is_int($key) || is_string($key)) {
                                    yield $key;
                                }

                                continue 5;
                            }
                        }
                    }
                }
            }
        }
    }
}
