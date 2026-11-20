<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Requests\Install;

use DbCms\Core\DTOs\Install\DatabaseSetupDTO;
use Illuminate\Foundation\Http\FormRequest;

final class DatabaseSetupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, string> */
    public function rules(): array
    {
        return ['host' => 'required|string|regex:/\A[A-Za-z0-9_.:\-\[\]]{1,253}\z/', 'port' => 'required|integer|between:1,65535', 'database' => 'required|string|regex:/\A[A-Za-z0-9_]{1,64}\z/', 'username' => 'required|string|max:255', 'password' => 'nullable|string|max:4096', 'auto_create' => 'nullable|boolean'];
    }

    public function toDto(): DatabaseSetupDTO
    {
        return new DatabaseSetupDTO($this->string('host')->toString(), $this->string('port')->toString(), $this->string('database')->toString(), $this->string('username')->toString(), $this->string('password')->toString(), $this->boolean('auto_create', true));
    }
}
