<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Requests\Admin;

use DbCms\Core\Services\Cms\SiteSettingsService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation for the general site & company settings form.
 */
class SiteSettingsUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The route is already protected by EnsureAdminAuthenticated + CheckPermission.
        return true;
    }

    /**
     * Checkboxes are absent when unchecked: normalise them to booleans.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'front_nav_admin_button' => $this->boolean('front_nav_admin_button'),
            'front_nav_toggle_enabled' => $this->boolean('front_nav_toggle_enabled'),
            'front_nav_toggle_desktop' => $this->boolean('front_nav_toggle_desktop'),
        ]);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        $rules = [];
        foreach (SiteSettingsService::rules() as $key => $constraints) {
            $rules[str_replace('.', '_', $key)] = $constraints;
        }

        return $rules;
    }

    /** @return array<string, mixed> */
    public function settings(): array
    {
        $validated = $this->validated();
        $attributes = [];
        foreach (SiteSettingsService::rules() as $key => $constraints) {
            $field = str_replace('.', '_', $key);
            if (array_key_exists($field, $validated)) {
                $attributes[$key] = $validated[$field];
            }
        }

        return $attributes;
    }
}
