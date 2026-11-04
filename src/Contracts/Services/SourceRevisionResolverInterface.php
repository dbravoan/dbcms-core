<?php

declare(strict_types=1);

namespace DbCms\Core\Contracts\Services;

interface SourceRevisionResolverInterface
{
    public function resolve(string $version): string;
}
