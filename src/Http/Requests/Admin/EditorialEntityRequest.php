<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class EditorialEntityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'audit_action' => 'nullable|in:draft,submit_for_review',
            'audit_notes' => 'nullable|string|max:2000',
            'translations' => 'required|array|min:1',
            'translations.*' => 'array',
            'translations.*.title' => 'nullable|string|max:255',
            'translations.*.slug' => 'nullable|string|max:255',
            'translations.*.content' => 'nullable|string',
            'translations.*.summary' => 'nullable|string|max:65535',
            'translations.*.meta_title' => 'nullable|string|max:255',
            'translations.*.meta_description' => 'nullable|string|max:65535',
            'translations.*.schema_type' => 'nullable|string|max:100',
            'category_id' => 'nullable|integer|exists:categories,id',
            'featured_image' => 'nullable|string|max:2048',
            'is_featured' => 'nullable|boolean',
            'is_published' => 'nullable|boolean',
            'allow_comments' => 'nullable|boolean',
            'template' => 'nullable|string|max:100',
            'layout' => 'nullable|string|max:100',
            'market_ids' => 'nullable|array',
            'market_ids.*' => 'integer|exists:markets,id',
            'sections_json' => 'nullable|string|json',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach ((array) $this->input('translations', []) as $translation) {
                if (is_array($translation) && is_string($translation['title'] ?? null) && trim($translation['title']) !== '') {
                    return;
                }
            }
            $validator->errors()->add('translations.title', 'El título es obligatorio.');
        });
    }
}
