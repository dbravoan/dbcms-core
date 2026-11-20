<?php

declare(strict_types=1);

namespace DbCms\Core\Contracts\Services;

interface GeoExportServiceInterface
{
    /** @return list<string> Exported private file paths. */
    public function export(?string $directory = null): array;
}
