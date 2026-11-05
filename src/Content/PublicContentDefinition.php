<?php

declare(strict_types=1);

namespace DbCms\Core\Content;

/** Explicit public discovery metadata; administrative entities are private by default. */
final readonly class PublicContentDefinition
{
    public function __construct(
        public string $segment,
        public string $icon = 'fa-solid fa-file-lines',
        public bool $includeHub = false,
        public ?string $slugPathMethod = null,
        public string $priority = '0.7',
        public string $changefreq = 'weekly',
    ) {}
}
