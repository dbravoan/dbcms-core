<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Controllers\Admin\BlockPackages;

use DbCms\Core\Blocks\BlockPackageManager;
use DbCms\Core\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

final class BlockPackagesGetController extends Controller
{
    public function __construct(private readonly BlockPackageManager $packages) {}

    public function __invoke(): View
    {
        return view('admin.block-packages.index', ['packages' => $this->packages->forAdministration()]);
    }
}
