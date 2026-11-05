<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Controllers\Install;

use DbCms\Core\Http\Controllers\Controller;
use DbCms\Core\Services\Install\ConfiguredDemoSeeder;
use Illuminate\Contracts\View\View;

final class MigrationGetController extends Controller
{
    public function __invoke(): View
    {
        return view('install.step3_migrate', ['demoAvailable' => ConfiguredDemoSeeder::resolve() !== null]);
    }
}
