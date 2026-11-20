<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class IntegrationGuideProgressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,list<string>> */
    public function rules(): array
    {
        return ['step_id' => ['required', 'string', 'max:64', 'regex:/\A[a-z0-9][a-z0-9_-]*\z/'], 'completed' => ['required', 'boolean']];
    }
}
