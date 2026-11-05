<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Integrations;

use DbCms\Core\Contracts\Services\IntegrationGuideServiceInterface;
use DbCms\Core\DTOs\Integrations\SetupGuideDescriptor;
use DbCms\Core\Integrations\IntegrationGuideRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class IntegrationGuideService implements IntegrationGuideServiceInterface
{
    public function __construct(private readonly IntegrationGuideRegistry $registry) {}

    /** @return array<string,mixed> */
    public function index(string $module, int $userId): array
    {
        $this->requireUser($userId);
        $guides = $this->registry->all($module)[$module] ?? [];
        if ($guides === []) {
            throw new HttpException(404, __('integrations.none_registered'));
        }

        return ['module' => $module, 'guides' => $guides];
    }

    /** @return array<string,mixed> */
    public function show(string $module, string $guide, int $userId): array
    {
        $this->requireUser($userId);
        $descriptor = $this->descriptor($module, $guide);
        $completedSteps = DB::table('integration_guide_progress')->where($this->identity($module, $descriptor, $userId))->orderBy('step_id')->pluck('step_id')->all();
        $callbackUrls = [];
        foreach ($descriptor->steps as $step) {
            if ($step->callbackRoute === null || ! str_ends_with($step->callbackRoute, '.oauth.callback')) {
                continue;
            }
            $callbackRoute = Route::getRoutes()->getByName($step->callbackRoute);
            if ($callbackRoute !== null && ! preg_match('#(?:^|/)oauth/(?:\{[^/]+\}|[a-z][a-z0-9_-]*)/callback$#D', $callbackRoute->uri())) {
                continue;
            }
            $callbackUrls[$step->id] = $callbackRoute !== null ? route($step->callbackRoute, $step->callbackParameters) : null;
        }

        return ['module' => $module, 'guide' => $descriptor, 'completedSteps' => $completedSteps, 'callbackUrls' => $callbackUrls];
    }

    public function updateProgress(string $module, string $guide, int $userId, string $stepId, bool $completed): void
    {
        $this->requireUser($userId);
        $descriptor = $this->descriptor($module, $guide);
        if (! in_array($stepId, array_column($descriptor->steps, 'id'), true)) {
            throw ValidationException::withMessages(['step_id' => __('integrations.step_not_found')]);
        }
        $identity = $this->identity($module, $descriptor, $userId) + ['step_id' => $stepId];
        DB::transaction(function () use ($identity, $completed): void {
            if ($completed) {
                DB::table('integration_guide_progress')->upsert([$identity + ['completed_at' => now()]], ['installation_id', 'user_id', 'module', 'guide_id', 'guide_version', 'step_id'], ['completed_at']);
            } else {
                DB::table('integration_guide_progress')->where($identity)->delete();
            }
        });
    }

    private function descriptor(string $module, string $guide): SetupGuideDescriptor
    {
        return $this->registry->find($module, $guide) ?? throw new HttpException(404, __('integrations.not_found'));
    }

    /** @return array<string,int|string> */
    private function identity(string $module, SetupGuideDescriptor $guide, int $userId): array
    {
        $installation = (string) config('cms.installation_id');
        if ($installation === '' || strlen($installation) > 128) {
            throw new \LogicException('A stable installation identity is required for guide progress.');
        }

        return ['installation_id' => $installation, 'user_id' => $userId, 'module' => $module, 'guide_id' => $guide->id, 'guide_version' => $guide->version];
    }

    private function requireUser(int $userId): void
    {
        if ($userId < 1) {
            throw new HttpException(403, __('integrations.admin_required'));
        }
    }
}
