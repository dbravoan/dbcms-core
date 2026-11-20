<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Controllers\Admin\Settings;

use DbCms\Core\Http\Controllers\Controller;
use DbCms\Core\Services\Privacy\LegalPageService;
use DbCms\Core\Services\Privacy\PrivacyConfigurationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class PrivacyGetController extends Controller
{
    public function __invoke(Request $request, LegalPageService $legal, PrivacyConfigurationService $configuration): View
    {
        $company = $legal->company();
        $processing = (array) ($company['processing'] ?? []);
        $owner = $request->query('owner');
        if (is_string($owner) && preg_match('/^[a-z][a-z0-9_\-]{0,79}$/', $owner) === 1 && ! collect($processing)->contains('owner', $owner)) {
            $processing[] = ['owner' => $owner];
        }
        $company['processing'] = $processing;

        return view('admin.settings.privacy', [
            'company' => $company,
            'revision' => $configuration->revision(),
        ]);
    }
}
