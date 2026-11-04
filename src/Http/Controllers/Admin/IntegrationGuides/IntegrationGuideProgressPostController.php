<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Controllers\Admin\IntegrationGuides;

use DbCms\Core\Contracts\Services\IntegrationGuideServiceInterface;
use DbCms\Core\Http\Controllers\Controller;
use DbCms\Core\Http\Requests\Admin\IntegrationGuideProgressRequest;
use Illuminate\Http\RedirectResponse;

final class IntegrationGuideProgressPostController extends Controller
{
    public function __construct(private readonly IntegrationGuideServiceInterface $service) {}

    public function __invoke(IntegrationGuideProgressRequest $request, string $identifier, string $guide): RedirectResponse
    {
        $this->service->updateProgress($identifier, $guide, (int) $request->user()?->getAuthIdentifier(), (string) $request->validated('step_id'), $request->boolean('completed'));

        return redirect()->route('admin.modules.guides.show', ['identifier' => $identifier, 'guide' => $guide])->with('success', __('integrations.saved'));
    }
}
