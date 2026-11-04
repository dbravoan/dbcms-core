<?php

declare(strict_types=1);

namespace DbCms\Core\DTOs;

/** Public release metadata shared by the administrative and console catalogues. */
final readonly class UpdateReleaseCandidate
{
    public function __construct(
        public string $version,
        public string $name,
        public string $body,
        public string $url,
        public ?string $publishedAt,
    ) {}

    /** @param array<string, mixed> $release */
    public static function fromRelease(array $release): self
    {
        return new self(
            (string) $release['version'],
            (string) ($release['name'] ?? $release['version']),
            (string) ($release['body'] ?? ''),
            (string) ($release['html_url'] ?? ''),
            is_string($release['published_at'] ?? null) ? $release['published_at'] : null,
        );
    }

    /** @return array{version:string,name:string,body:string,html_url:string,published_at:?string} */
    public function toArray(): array
    {
        return ['version' => $this->version, 'name' => $this->name, 'body' => $this->body, 'html_url' => $this->url, 'published_at' => $this->publishedAt];
    }
}
