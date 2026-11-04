<?php

declare(strict_types=1);

namespace DbCms\Core\Contracts\Services;

interface IntegrationGuideServiceInterface
{
    /** @return array<string,mixed> */
    public function index(string $module, int $userId): array;

    /** @return array<string,mixed> */
    public function show(string $module, string $guide, int $userId): array;

    public function updateProgress(string $module, string $guide, int $userId, string $stepId, bool $completed): void;
}
