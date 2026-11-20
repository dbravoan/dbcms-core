<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Controllers\Install;

use DbCms\Core\Contracts\Services\InstallationAdminServiceInterface;
use DbCms\Core\Http\Controllers\Controller;
use DbCms\Core\Http\Requests\Install\InstallationAdminRequest;
use DbCms\Core\Services\Install\ProjectReadme;
use Illuminate\Http\RedirectResponse;

final class AdminStorePostController extends Controller
{
    public function __construct(private readonly InstallationAdminServiceInterface $service, private readonly ProjectReadme $readme) {}

    public function __invoke(InstallationAdminRequest $request): RedirectResponse
    {
        $data = $request->toDto();
        $this->service->finalize($data);
        $response = redirect()->route('install.step5')->with('install.brand', $data->brand)
            ->with('install.just_installed', true)->with('install.site_name', $data->siteName);
        if ($this->readme->isPending(base_path('README.md'))) {
            $response->with('install.readme_identity', [
                'site_name' => $data->siteName,
                'holder' => $data->company?->values['company_name'],
            ]);
        }

        return $response;
    }
}
