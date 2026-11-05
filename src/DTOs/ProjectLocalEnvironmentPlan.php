<?php

declare(strict_types=1);

namespace DbCms\Core\DTOs;

final readonly class ProjectLocalEnvironmentPlan
{
    /** @param array<string, int> $ports
     * @param  array<string, string>  $urls
     */
    public function __construct(
        public string $root,
        public string $projectId,
        public array $ports,
        public array $urls,
        public bool $createsEnvironment,
        public ?string $environmentHash,
    ) {}
}
