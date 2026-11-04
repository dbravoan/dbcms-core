<?php

declare(strict_types=1);

namespace DbCms\Core\DTOs\Seo;

use DbCms\Core\DTOs\BaseDTO;
use DbCms\Core\Models\Market;
use Illuminate\Support\Collection;

final readonly class SeoAuditReportDTO extends BaseDTO
{
    /** @param list<array<string, mixed>> $auditedEntities
     * @param  array<string, array{label: string, count: int}>  $counts
     * @param  Collection<int, Market>  $markets
     */
    public function __construct(
        public array $auditedEntities,
        public array $counts,
        public Collection $markets,
        public string $siteSchema,
        public bool $geoRoutesAvailable,
    ) {}
}
