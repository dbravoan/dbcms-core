<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Controllers\Admin\BlockPackages;

use DbCms\Core\Blocks\BlockPackageManager;
use DbCms\Core\Http\Controllers\Controller;
use DbCms\Core\Http\Requests\Admin\BlockPackageToggleRequest;
use Illuminate\Http\RedirectResponse;

final class BlockPackageTogglePostController extends Controller
{
    public function __construct(private readonly BlockPackageManager $packages) {}

    public function __invoke(BlockPackageToggleRequest $request, string $identifier): RedirectResponse
    {
        $this->packages->setEnabled($identifier, $request->boolean('enabled'));

        return back()->with('success', 'Disponibilidad del paquete actualizada. El contenido se conserva.');
    }
}
