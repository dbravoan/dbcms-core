<?php

declare(strict_types=1);

namespace DbCms\Core\Forms\Http\Requests;

use DbCms\Core\Forms\Http\Controllers\Admin\Concerns\ValidatesFormPayload;
use DbCms\Core\Forms\Models\CustomForm;
use DbCms\Core\Forms\Services\FormDefinitionService;
use Illuminate\Foundation\Http\FormRequest;

final class FormMutationRequest extends FormRequest
{
    use ValidatesFormPayload;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        $form = $this->route('form');

        return $this->formValidationRules($form instanceof CustomForm ? $form : null);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(app(FormDefinitionService::class)->decode($this->all()));
        $this->merge(['is_active' => $this->boolean('is_active', $this->isMethod('POST'))]);
    }
}
