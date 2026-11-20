<?php

declare(strict_types=1);

namespace DbCms\Core\Capabilities;

final readonly class ActorContext
{
    /**
     * @param  list<string>  $scopes
     */
    public function __construct(
        public ?int $userId,
        public string $origin = 'web',
        public array $scopes = [],
        public ?string $clientId = null,
        public ?string $tokenId = null,
        public bool $isService = false,
        public string $market = 'es',
        public string $locale = 'es',
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            isset($data['userId']) ? (int) $data['userId'] : null,
            (string) ($data['origin'] ?? 'web'),
            array_values(array_map('strval', (array) ($data['scopes'] ?? []))),
            isset($data['clientId']) ? (string) $data['clientId'] : null,
            isset($data['tokenId']) ? (string) $data['tokenId'] : null,
            (bool) ($data['isService'] ?? false),
            (string) ($data['market'] ?? 'es'),
            (string) ($data['locale'] ?? 'es'),
        );
    }
}
