<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Controllers\Admin\Settings;

use DbCms\Core\Http\Controllers\Controller;
use DbCms\Core\Maintenance\Contracts\MaintenanceSettingsAdministrationInterface;
use DbCms\Core\Services\Cms\CoreFeatureSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class CoreFeaturePutController extends Controller
{
    public function __invoke(Request $request, string $feature, CoreFeatureSettings $settings): RedirectResponse
    {
        abort_unless(is_array(config('core_features.'.$feature)), 404);
        $values = $request->except(['_token', '_method']);
        foreach ($settings->getSchema($feature) as $key => $field) {
            if (($field['type'] ?? '') === 'boolean') {
                $values[$key] = $request->boolean($key);
            }
        }
        if ($feature === 'maintenance') {
            app(MaintenanceSettingsAdministrationInterface::class)->update($values);
        } else {
            $settings->setMany($feature, $values);
        }

        return back()->with('success', __('admin.actions.save'));
    }
}
