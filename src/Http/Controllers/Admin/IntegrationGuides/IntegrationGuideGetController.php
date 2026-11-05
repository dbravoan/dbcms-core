<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Controllers\Admin\IntegrationGuides;

use DbCms\Core\Contracts\Services\IntegrationGuideServiceInterface;
use DbCms\Core\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class IntegrationGuideGetController extends Controller
{
    public function __construct(private readonly IntegrationGuideServiceInterface $service) {}

    public function __invoke(Request $request, string $identifier, string $guide): View
    {
        return view('admin.integrations.show', $this->service->show($identifier, $guide, (int) $request->user()?->getAuthIdentifier()));
    }
}
