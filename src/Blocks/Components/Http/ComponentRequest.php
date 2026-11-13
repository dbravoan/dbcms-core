<?php

declare(strict_types=1);

namespace DbCms\Core\Blocks\Components\Http;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class ComponentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return ['translations' => 'required|array|min:1', 'translations.*' => 'array', 'translations.*.title' => 'nullable|string|max:255', 'translations.*.slug' => 'nullable|string|max:255',
            'is_active' => 'required|boolean', 'is_published' => 'required|boolean', 'exposed_properties' => 'required|string|json',
            'market_ids' => 'sometimes|array', 'market_ids.*' => 'integer|exists:markets,id', 'sections_json' => 'nullable|string|json', 'sections_order_locale' => 'nullable|string|max:20', 'audit_action' => 'nullable|in:draft,submit_for_review', 'audit_notes' => 'nullable|string|max:2000'];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach ((array) $this->input('translations', []) as $translation) {
                if (is_array($translation) && trim((string) ($translation['title'] ?? '')) !== '') {
                    return;
                }
            }
            $validator->errors()->add('translations.title', 'Indica un nombre al menos en un idioma.');
        });
    }
}
