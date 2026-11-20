<?php

declare(strict_types=1);

namespace DbCms\Core\Forms\Services;

use DbCms\Core\Forms\Models\CustomForm;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Presentation references canonical fields; validation remains owned by field types. */
final class FormDefinitionService
{
    /** @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function decode(array $input): array
    {
        foreach (['layout', 'actions'] as $key) {
            if (! is_string($input[$key] ?? null)) {
                continue;
            }
            try {
                $input[$key] = json_decode($input[$key], true, 32, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                throw ValidationException::withMessages([$key => 'La composición debe ser JSON válido.']);
            }
        }

        return $input;
    }

    /** @param array<string,mixed> $input
     * @param  list<string>  $fieldKeys
     */
    public function validate(array $input, array $fieldKeys, ?CustomForm $form): void
    {
        Validator::make($input, [
            'layout' => 'nullable|array:steps',
            'layout.steps' => 'required_with:layout|array|list|min:1|max:20',
            'layout.steps.*' => 'array:key,labels,groups',
            'layout.steps.*.key' => 'required|string|regex:/^[a-z][a-z0-9_-]*$/D|max:50|distinct',
            'layout.steps.*.labels' => 'nullable|array',
            'layout.steps.*.labels.*' => 'string|max:150',
            'layout.steps.*.groups' => 'required|array|list|min:1|max:50',
            'layout.steps.*.groups.*' => 'array:key,labels,columns,fields',
            'layout.steps.*.groups.*.key' => 'required|string|regex:/^[a-z][a-z0-9_-]*$/D|max:50',
            'layout.steps.*.groups.*.labels' => 'nullable|array',
            'layout.steps.*.groups.*.labels.*' => 'string|max:150',
            'layout.steps.*.groups.*.columns' => 'required|array:mobile,tablet,desktop',
            'layout.steps.*.groups.*.columns.mobile' => 'required|integer|min:1|max:2',
            'layout.steps.*.groups.*.columns.tablet' => 'required|integer|min:1|max:4',
            'layout.steps.*.groups.*.columns.desktop' => 'required|integer|min:1|max:4',
            'layout.steps.*.groups.*.fields' => 'required|array|list|min:1',
            'layout.steps.*.groups.*.fields.*' => ['required', 'string', Rule::in($fieldKeys)],
            'actions' => 'nullable|array|list|max:20',
            'actions.*' => 'array:id,type,target',
            'actions.*.id' => 'required|string|regex:/^[a-z][a-z0-9_-]*$/D|max:50|distinct',
            'actions.*.type' => ['required', Rule::in(['confirm', 'redirect', 'email', 'webhook', 'open_popup', 'close_popup'])],
            'actions.*.target' => 'nullable|string|max:1000',
        ])->validate();
        $form?->loadDefinitionAttributes();
        $layout = array_key_exists('layout', $input) ? $input['layout'] : $form?->layout;
        if (is_array($layout) && $layout !== []) {
            $references = [];
            foreach ($layout['steps'] as $step) {
                foreach ($step['groups'] as $group) {
                    $references = array_merge($references, $group['fields']);
                }
            }
            $expected = $fieldKeys;
            sort($references);
            sort($expected);
            if ($references !== $expected) {
                throw ValidationException::withMessages(['layout' => 'La disposición debe referenciar cada field_key exactamente una vez.']);
            }
        }
        $actions = $input['actions'] ?? [];
        if (! array_key_exists('actions', $input) && $form !== null) {
            $actions = $form->actions ?? [];
        }
        foreach ($actions as $index => $action) {
            $target = (string) ($action['target'] ?? '');
            $valid = match ($action['type']) {
                'redirect' => $this->internalPath($target),
                'open_popup', 'close_popup' => preg_match('/^[a-zA-Z][a-zA-Z0-9_-]{0,99}$/D', $target) === 1,
                'webhook' => app(FormWebhookPolicy::class)->available($target),
                'email' => $target === '' && filter_var(array_key_exists('notification_email', $input) ? $input['notification_email'] : $form?->notification_email, FILTER_VALIDATE_EMAIL) !== false,
                default => $target === '',
            };
            if (! $valid) {
                throw ValidationException::withMessages(['actions.'.$index.'.target' => 'Configura un destino permitido y su tratamiento revisado.']);
            }
        }
    }

    public function internalPath(string $path): bool
    {
        return str_starts_with($path, '/') && ! str_starts_with($path, '//')
            && ! str_contains($path, '\\') && preg_match('/[\x00-\x20\x7f]/', $path) !== 1
            && ! str_contains($path, '%') && parse_url($path, PHP_URL_HOST) === null;
    }
}
