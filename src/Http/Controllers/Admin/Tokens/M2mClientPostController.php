<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Controllers\Admin\Tokens;

use DbCms\Core\Http\Controllers\Controller;
use DbCms\Core\Services\Auth\ServiceAccountProvisioningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class M2mClientPostController extends Controller
{
    public function __construct(
        private readonly ServiceAccountProvisioningService $serviceAccounts
    ) {}

    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'scopes' => ['nullable', 'array'],
            'scopes.*' => ['string'],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
        ]);

        $name = (string) $validated['name'];
        $scopes = (array) ($validated['scopes'] ?? []);

        $client = $this->serviceAccounts->create($name, array_values($scopes), (int) $validated['role_id']);
        $secret = $client->plainSecret ?? '';

        return redirect()->route('admin.tokens.index')->with([
            'success' => "Cliente M2M '{$name}' creado exitosamente.",
            'issued_m2m_client' => [
                'name' => $name,
                'client_id' => $client->id,
                'client_secret' => $secret,
                'suggested_scopes' => implode(' ', $scopes),
            ],
        ]);
    }
}
