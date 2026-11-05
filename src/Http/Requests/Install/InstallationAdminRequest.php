<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Requests\Install;

use DbCms\Core\DTOs\Install\InstallationAdminDTO;
use DbCms\Core\DTOs\Install\LegalCompanyDTO;
use DbCms\Core\Services\Install\BrandRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class InstallationAdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(BrandRegistry $brands): array
    {
        return ['company' => ['required', 'array'], 'name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255', 'unique:users,email'], 'password' => ['required', 'string', 'min:8', 'confirmed'], 'site_name' => ['required', 'string', 'max:255'], 'app_url' => ['nullable', 'url:http,https', 'max:2048'], 'admin_path' => ['required', 'string', 'regex:/^[a-zA-Z0-9_\-]+$/'], 'brand' => ['nullable', 'string', Rule::in($brands->brandIdentifiers())], 'front_theme' => ['nullable', 'string', Rule::in($brands->frontThemeIdentifiers())]];
    }

    public function toDto(): InstallationAdminDTO
    {
        return new InstallationAdminDTO($this->string('name')->toString(), $this->string('email')->toString(), $this->string('password')->toString(), $this->string('site_name')->toString(), $this->string('admin_path')->toString(), $this->string('brand', (string) config('cms.admin_theme', 'dbcms'))->toString(), $this->string('front_theme', (string) config('cms.theme', 'db-cyber'))->toString(), rtrim($this->string('app_url', (string) config('app.url'))->toString(), '/'), LegalCompanyDTO::fromArray($this->array('company')));
    }
}
