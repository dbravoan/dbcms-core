<?php

declare(strict_types=1);

namespace DbCms\Core\DTOs\Modules;

final readonly class ModuleSettingsUpdateDTO
{
    /** @param array<string, mixed> $values */
    public function __construct(public array $values) {}
}
