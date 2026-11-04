<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Controllers\Admin\Auth;

use DbCms\Core\Http\Controllers\Controller;
use DbCms\Core\Models\User;
use DbCms\Core\Services\Auth\DemoPersonaAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Local-only persona switcher for demos and development.
 *
 * Hardened: it requires BOTH `local` (or `testing`) environment AND the explicit
 * `cms.quick_login` flag, resolves only the declared demo accounts (never an
 * arbitrary fallback user) and reuses the admin surface as a normal login.
 */
final class QuickLoginGetController extends Controller
{
    public function __construct(
        private readonly DemoPersonaAccess $demoAccess,
    ) {}

    public function __invoke(Request $request, string $role): RedirectResponse
    {
        if (! $this->demoAccess->enabled()) {
            return redirect()->route('admin.login')->withErrors([
                'email' => 'El acceso rápido de personas demo no está disponible en este entorno.',
            ]);
        }

        if (! array_key_exists($role, DemoPersonaAccess::PERSONAS)) {
            return redirect()->route('admin.login')->withErrors([
                'email' => 'Persona demo desconocida.',
            ]);
        }

        $user = User::where('email', DemoPersonaAccess::PERSONAS[$role])->first();

        if (! $user) {
            return redirect()->route('admin.login')->withErrors([
                'email' => 'Usuario demo no encontrado. Ejecuta el seeder de demostración.',
            ]);
        }

        $request->session()->regenerate();
        Auth::guard('web')->login($user, true);
        $request->session()->save();

        return redirect()->route('admin.dashboard');
    }
}
