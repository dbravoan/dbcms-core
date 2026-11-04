<?php

declare(strict_types=1);

namespace DbCms\Core\Search\Contracts;

use DbCms\Core\Content\EntitySchema;
use DbCms\Core\Search\SearchDocument;

/**
 * A module-owned source of searchable content.
 *
 * The module knows how to turn its own models into {@see SearchDocument}
 * instances (texts, canonical URL, relevance weight); the core only indexes and
 * ranks them, so adding a searchable entity never touches the search engine.
 */
interface SearchSourceInterface
{
    /**
     * Entity type key, matching the registered {@see EntitySchema}.
     */
    public function type(): string;

    /**
     * Localized singular label, used in result badges and facets.
     */
    public function label(string $locale): string;

    /**
     * Relative relevance multiplier of the whole entity (a product naturally
     * outweighs a tag). 1.0 by default.
     */
    public function weight(): float;

    /**
     * Every indexable document of this source.
     *
     * @return iterable<int, SearchDocument>
     */
    public function documents(?string $locale = null): iterable;

    /**
     * Documents of a single entity (all active locales), used by the incremental
     * reindex triggered when content changes.
     *
     * @return iterable<int, SearchDocument>
     */
    public function documentsFor(int|string $entityId): iterable;
}
