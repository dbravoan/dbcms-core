<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Controllers\Install;

use DbCms\Core\Services\Install\InstallationAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class InstallationAccessPostController
{
    public function __invoke(Request $request, InstallationAccess $access): RedirectResponse
    {
        if ($access->granted($request) || $access->grant($request)) {
            return redirect()->route('install.step1');
        }

        return redirect()->route('install.access')->withErrors(['install_token' => 'El código no es válido o ya se ha utilizado.']);
    }
}
