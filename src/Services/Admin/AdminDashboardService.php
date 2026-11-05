<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Admin;

use DbCms\Core\Content\Pages\Models\Page;
use DbCms\Core\Contracts\Services\AdminDashboardServiceInterface;
use DbCms\Core\Models\CmsModule;
use DbCms\Core\Models\Market;
use DbCms\Core\Models\User;
use DbCms\Core\Modules\ModuleManager;

final class AdminDashboardService implements AdminDashboardServiceInterface
{
    public function __construct(private readonly ModuleManager $modules) {}

    /** @return array<string, mixed> */
    public function data(User $user): array
    {
        $fresh = User::findOrFail((int) $user->getKey());
        abort_unless(! $fresh->is_service_account && ($fresh->isAdmin() || $fresh->hasPermission('access:admin')), 403);
        $allowed = static fn (string $permission): bool => $fresh->isAdmin() || $fresh->hasPermission($permission);
        $contributions = [];
        foreach ($this->modules->getActiveModules() as $module) {
            $contribution = $module->getAdminDashboard($fresh);
            if ($contribution !== null) {
                $contributions[] = $contribution;
            }
        }

        return [
            'stats' => [
                'total_users' => $allowed('manage:users') ? User::count() : 0,
                'active_markets' => $allowed('manage:modules') ? Market::where('is_active', true)->count() : 0,
                'installed_modules' => $allowed('manage:modules') ? CmsModule::where('is_installed', true)->count() : 0,
                'total_pages' => $allowed('manage:pages') ? Page::count() : 0,
            ],
            'moduleDashboard' => $contributions,
            'hasPages' => $allowed('manage:pages'),
            'canManageModules' => $allowed('manage:modules'),
            'canManageUsers' => $allowed('manage:users'),
            'hasSeo' => $allowed('manage:seo'),
        ];
    }
}
