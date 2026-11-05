<?php

declare(strict_types=1);

namespace DbCms\Core\Search;

/**
 * A single ranked search result.
 */
final readonly class SearchHit
{
    public function __construct(
        public string $type,
        public string $label,
        public string $title,
        public string $excerpt,
        public string $url,
        public float $relevance = 0.0,
        public ?string $imageUrl = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'label' => $this->label,
            'title' => $this->title,
            'excerpt' => $this->excerpt,
            'url' => $this->url,
            'relevance' => round($this->relevance, 4),
            'image_url' => $this->imageUrl,
        ];
    }
}
