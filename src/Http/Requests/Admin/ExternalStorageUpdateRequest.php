<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Requests\Admin;

use DbCms\Core\Services\Storage\ExternalStorageService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for the external storage connections form (AWS / OVH / R2...).
 */
class ExternalStorageUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The route is protected by EnsureAdminAuthenticated + CheckPermission.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'provider' => ['required', 'string', Rule::in(array_keys(ExternalStorageService::PROVIDERS))],
            'endpoint' => ['nullable', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'max:80'],
            'bucket' => ['nullable', 'string', 'max:120'],
            'access_key' => ['nullable', 'string', 'max:255'],
            // Masked placeholder keeps the stored secret untouched.
            'secret_key' => ['nullable', 'string', 'max:255'],
            'path_style' => ['nullable', 'boolean'],
            'public_url' => ['nullable', 'url', 'max:255'],
            'root' => ['nullable', 'string', 'max:120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'provider' => __('admin.connections.field_provider'),
            'endpoint' => __('admin.connections.field_endpoint'),
            'region' => __('admin.connections.field_region'),
            'bucket' => __('admin.connections.field_bucket'),
            'access_key' => __('admin.connections.field_access_key'),
            'secret_key' => __('admin.connections.field_secret_key'),
            'public_url' => __('admin.connections.field_public_url'),
            'root' => __('admin.connections.field_root'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['path_style' => $this->boolean('path_style')]);
    }
}
