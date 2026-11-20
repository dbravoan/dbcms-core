<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Controllers\Admin\Auth;

use DbCms\Core\Http\Controllers\Controller;
use DbCms\Core\Services\Auth\DemoPersonaAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

final class LoginGetController extends Controller
{
    public function __invoke(DemoPersonaAccess $demoAccess): View|RedirectResponse
    {
        if (Auth::guard('web')->check()) {
            return redirect()->route('admin.dashboard');
        }

        $adminPath = config('cms.admin_path', 'admin');

        return view('admin.auth.login', [
            'adminPath' => $adminPath,
            'demoPersonas' => $demoAccess->availablePersonas(),
        ]);
    }
}
