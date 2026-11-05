<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Cms;

use DbCms\Core\Contracts\Services\ModuleSettingsAdministrationInterface;
use DbCms\Core\Contracts\Services\ModuleSettingsServiceInterface;
use DbCms\Core\DTOs\Modules\ModuleSettingsUpdateDTO;
use DbCms\Core\DTOs\Modules\ModuleSettingsViewDTO;
use DbCms\Core\Models\CmsModule;
use DbCms\Core\Modules\ModuleManager;
use DbCms\Core\Modules\ModuleSettingsExtensionRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class ModuleSettingsAdministrationService implements ModuleSettingsAdministrationInterface
{
    public function __construct(private readonly ModuleSettingsServiceInterface $settings, private readonly ModuleManager $modules, private readonly ModuleSettingsExtensionRegistry $extensions) {}

    public function view(string $identifier): ModuleSettingsViewDTO
    {
        $module = $this->available($identifier);
        $customView = $identifier.'::admin.settings';
        $view = view()->exists($customView) ? $customView : 'admin.modules.settings';

        return new ModuleSettingsViewDTO($view, array_merge([
            'module' => $module,
            'schema' => $this->settings->getSchema($identifier),
            'settings' => $this->settings->getAll($identifier),
            'configured' => $this->settings->isConfigured($identifier),
            'inactive' => ! $module->is_active,
        ], $this->extensions->view($identifier)));
    }

    public function update(string $identifier, ModuleSettingsUpdateDTO $settings): string
    {
        $module = $this->available($identifier);
        DB::transaction(function () use ($identifier, $settings): void {
            if ($this->extensions->update($identifier, $settings)) {
                return;
            }
            $schema = $this->settings->getSchema($identifier);
            $rules = [];
            $payload = $settings->values;
            foreach ($schema as $key => $field) {
                $type = $field['type'] ?? 'text';
                $required = (bool) ($field['required'] ?? false);
                $hasSecret = (bool) ($field['secret'] ?? false) && ! empty($this->settings->get($identifier, $key));
                $fieldRules = [$required && ! $hasSecret ? 'required' : 'nullable'];
                if ($type === 'boolean') {
                    $payload[$key] = filter_var($payload[$key] ?? false, FILTER_VALIDATE_BOOLEAN);
                    $fieldRules[] = 'boolean';
                } elseif ($type === 'number') {
                    $fieldRules[] = 'numeric';
                } elseif ($type === 'multiselect' || str_ends_with($type, '_multiselect')) {
                    $fieldRules[] = 'array';
                } else {
                    $fieldRules[] = 'string';
                    if (in_array($type, ['email', 'url'], true)) {
                        $fieldRules[] = $type;
                    }
                }
                $rules[$key] = $fieldRules;
            }
            $validated = Validator::make($payload, $rules)->validate();
            try {
                $this->settings->setMany($identifier, $validated);
            } catch (\InvalidArgumentException|\DomainException $error) {
                throw ValidationException::withMessages(['settings' => $error->getMessage()]);
            }
        });

        return $module->name;
    }

    private function available(string $identifier): CmsModule
    {
        // Settings are administration of an installed package; runtime stays behind module.enabled.
        $module = CmsModule::where('identifier', $identifier)->where('is_installed', true)->first();
        if ($module === null || ! $this->modules->isModuleDiscovered($identifier)) {
            abort(404, "Módulo '{$identifier}' no instalado o no encontrado.");
        }

        return $module;
    }
}
