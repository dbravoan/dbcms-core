<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Controllers\Admin\Modules;

use DbCms\Core\Http\Controllers\Controller;
use DbCms\Core\Modules\ModuleManager;
use Illuminate\Http\JsonResponse;

final class ModuleImpactGetController extends Controller
{
    public function __construct(
        private readonly ModuleManager $moduleManager
    ) {}

    public function __invoke(string $identifier): JsonResponse
    {
        abort_unless($this->moduleManager->isModuleDiscovered($identifier), 404);
        $impact = $this->moduleManager->getDeactivationImpact($identifier);

        return response()->json($impact);
    }
}
