<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Controllers\Admin\Settings;

use DbCms\Core\DTOs\Install\LegalCompanyDTO;
use DbCms\Core\Http\Controllers\Controller;
use DbCms\Core\Services\Privacy\PrivacyConfigurationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class PrivacyPutController extends Controller
{
    public function __invoke(Request $request, PrivacyConfigurationService $configuration): RedirectResponse
    {
        $input = $request->validate([
            'revision' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/'],
            'company' => ['required', 'array'],
        ]);

        try {
            $configuration->configure(LegalCompanyDTO::fromArray($input['company']), $input['revision']);
        } catch (\DomainException $error) {
            throw ValidationException::withMessages(['privacy' => $error->getMessage()]);
        }

        return redirect()->route('admin.settings.privacy')->with('success', 'Datos de privacidad actualizados correctamente.');
    }
}
