<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Seo;

use Carbon\CarbonImmutable;
use DbCms\Core\Contracts\SectionContentVisibility;
use Illuminate\Database\Eloquent\Model;

/** Timestamp of the public representation, without mutating approval history. */
final class PublicContentModifiedService
{
    public function forEntity(Model $entity): ?CarbonImmutable
    {
        $timestamps = [$entity->getAttribute('updated_at')];
        foreach (['translations', 'publishedSections', 'markets', 'items'] as $relation) {
            if ($relation === 'publishedSections' && $entity instanceof SectionContentVisibility && ! $entity->usesSectionContent()) {
                continue;
            }
            if (! method_exists($entity, $relation)) {
                continue;
            }
            $entity->loadMissing($relation === 'publishedSections' ? 'publishedSections.translations' : $relation);
            $relatedModels = $entity->getRelationValue($relation);
            if (! is_iterable($relatedModels)) {
                continue;
            }
            foreach ($relatedModels as $related) {
                if (! $related instanceof Model) {
                    continue;
                }
                $timestamps[] = $related->getAttribute('updated_at');
                if ($relation === 'publishedSections' && method_exists($related, 'translations')) {
                    $translations = $related->getRelationValue('translations');
                    if (is_iterable($translations)) {
                        foreach ($translations as $translation) {
                            if ($translation instanceof Model) {
                                $timestamps[] = $translation->getAttribute('updated_at');
                            }
                        }
                    }
                }
            }
        }
        $latest = null;
        foreach ($timestamps as $timestamp) {
            if ($timestamp === null) {
                continue;
            }
            $candidate = CarbonImmutable::parse($timestamp);
            if ($latest === null || $candidate->greaterThan($latest)) {
                $latest = $candidate;
            }
        }

        return $latest;
    }
}
