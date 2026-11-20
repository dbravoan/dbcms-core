<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Controllers\Admin\Settings;

use DbCms\Core\Http\Controllers\Controller;
use DbCms\Core\Maintenance\Contracts\MaintenanceSettingsAdministrationInterface;
use DbCms\Core\Services\Cms\CoreFeatureSettings;
use Illuminate\Contracts\View\View;

final class CoreFeatureGetController extends Controller
{
    public function __invoke(string $feature, CoreFeatureSettings $settings): View
    {
        abort_unless(is_array(config('core_features.'.$feature)), 404);
        if ($feature === 'maintenance') {
            return view('maintenance::admin.settings', app(MaintenanceSettingsAdministrationInterface::class)->view());
        }
        $schema = $settings->getSchema($feature);
        $values = $settings->getAll($feature);
        foreach ($schema as $key => $field) {
            if ($field['secret'] ?? false) {
                $values[$key] = empty($values[$key]) ? '' : '***';
            }
        }

        return view('admin.settings.feature', [
            'feature' => $feature, 'schema' => $schema, 'settings' => $values,
            'title' => config('core_features.'.$feature.'.name'),
        ]);
    }
}
