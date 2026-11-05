<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Controllers\Admin\Modules;

use DbCms\Core\Contracts\Services\ModuleSettingsServiceInterface;
use DbCms\Core\Http\Controllers\Controller;
use DbCms\Core\Models\CmsModule;
use DbCms\Core\Modules\ModuleManager;
use Illuminate\Contracts\View\View;

final class ModulesGetController extends Controller
{
    public function __construct(
        private readonly ModuleManager $moduleManager,
        private readonly ModuleSettingsServiceInterface $settings
    ) {}

    public function __invoke(): View
    {
        $modules = $this->moduleManager->catalog();

        return view('admin.modules.index', [
            'modules' => $modules,
            'configuration' => $modules->mapWithKeys(fn (CmsModule $module): array => [$module->identifier => [
                'hasSchema' => $module->is_installed && $this->settings->getSchema($module->identifier) !== [],
                'configured' => $this->settings->isConfigured($module->identifier),
            ]]),
        ]);
    }
}
