<?php

declare(strict_types=1);

namespace DbCms\Core\Search;

/**
 * One indexable entity translation.
 *
 * Modules produce these from their own models (they own the extraction logic),
 * and the core indexes, ranks and serves them. The URL is already the canonical
 * front-office URL of the entity in that locale, so search results never need
 * to know how a module builds its paths.
 */
final readonly class SearchDocument
{
    public function __construct(
        public string $type,
        public string $entityId,
        public string $locale,
        public string $url,
        public string $title,
        public string $excerpt = '',
        public string $body = '',
        public float $weight = 1.0,
        public ?string $market = null,
        public ?string $imageUrl = null,
    ) {}

    /**
     * Stable identity of a document: the same entity + locale always maps to the
     * same index row (upserts are therefore idempotent).
     */
    public function signature(): string
    {
        return $this->type.':'.$this->entityId.':'.$this->locale;
    }

    /**
     * @return array<string, mixed>
     */
    public function toIndexAttributes(): array
    {
        return [
            'type' => $this->type,
            'entity_id' => $this->entityId,
            'locale' => $this->locale,
            'market_code' => $this->market ?? '',
            'url' => $this->url,
            'title' => mb_substr($this->title, 0, 500),
            'excerpt' => mb_substr($this->excerpt, 0, 1000),
            'body' => $this->body,
            'weight' => $this->weight,
            'image_url' => $this->imageUrl,
        ];
    }
}
