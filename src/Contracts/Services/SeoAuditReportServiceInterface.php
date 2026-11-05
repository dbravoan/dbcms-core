<?php

declare(strict_types=1);

namespace DbCms\Core\Contracts\Services;

use DbCms\Core\DTOs\Seo\SeoAuditReportDTO;

interface SeoAuditReportServiceInterface
{
    public function report(): SeoAuditReportDTO;
}
