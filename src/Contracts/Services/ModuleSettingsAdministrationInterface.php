<?php

declare(strict_types=1);

namespace DbCms\Core\Contracts\Services;

use DbCms\Core\DTOs\Modules\ModuleSettingsUpdateDTO;
use DbCms\Core\DTOs\Modules\ModuleSettingsViewDTO;

interface ModuleSettingsAdministrationInterface
{
    public function view(string $identifier): ModuleSettingsViewDTO;

    public function update(string $identifier, ModuleSettingsUpdateDTO $settings): string;
}
