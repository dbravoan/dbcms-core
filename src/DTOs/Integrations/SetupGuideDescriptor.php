<?php

declare(strict_types=1);

namespace DbCms\Core\DTOs\Integrations;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class SetupGuideDescriptor
{
    /** @param list<string> $prerequisites
     * @param  list<GuideStep>  $steps
     * @param  list<string>  $troubleshooting
     * @param  array<string,string>  $officialSources
     */
    public function __construct(
        public string $id,
        public string $platform,
        public string $title,
        public array $prerequisites,
        public array $steps,
        public array $troubleshooting,
        public array $officialSources,
        public string $reviewedAt,
        public string $version = '1',
    ) {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $reviewedAt);
        if (! preg_match('/\A[a-z0-9][a-z0-9_-]{0,63}\z/', $id) || trim($title) === '' || trim($platform) === ''
            || ! preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9._-]{0,39}\z/', $version)
            || $date === false || $date->format('Y-m-d') !== $reviewedAt || $steps === []) {
            throw new InvalidArgumentException('Guide descriptors require identity, version, review date and ordered steps.');
        }
        $ids = array_map(static fn (GuideStep $step): string => $step->id, $steps);
        if (count(array_unique($ids)) !== count($ids)) {
            throw new InvalidArgumentException('Guide step IDs must be unique.');
        }
        GuideStep::validateLinks($officialSources);
    }
}
