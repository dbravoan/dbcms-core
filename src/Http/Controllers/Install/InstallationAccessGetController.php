<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Controllers\Install;

use DbCms\Core\Services\Install\InstallationAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class InstallationAccessGetController
{
    public function __invoke(Request $request, InstallationAccess $access): Response|RedirectResponse
    {
        if ($access->granted($request)) {
            return redirect()->route('install.step1');
        }

        return response()->view('install.access', ['configured' => $access->configured()])->header('Cache-Control', 'private, no-store');
    }
}
