<?php

declare(strict_types=1);

namespace DbCms\Core\Forms\Support;

use DbCms\Core\Forms\FieldTypeRegistry;

/**
 * Builds the field type list offered by the backoffice form builder.
 *
 * Driven by the {@see FieldTypeRegistry}, so a module that is disabled (or not
 * installed) simply disappears from the builder — the reason the `captcha`
 * option is supplied by the configured core anti-spam provider.
 */
final class FieldTypeOptions
{
    public function __construct(
        private readonly FieldTypeRegistry $registry,
    ) {}

    /**
     * @return array<int, array{key: string, label: string, add: string}>
     */
    public function list(): array
    {
        /** @var array<string, string> $labels */
        $labels = (array) config('forms.labels', []);

        /** @var array<string, string> $addLabels */
        $addLabels = (array) config('forms.add_labels', []);

        $options = [];

        foreach (array_keys($this->registry->available()) as $key) {
            $label = $this->translate($labels[$key] ?? null) ?? ucfirst($key);

            $options[] = [
                'key' => $key,
                'label' => $label,
                'add' => $this->translate($addLabels[$key] ?? null) ?? '+ '.$label,
            ];
        }

        return $options;
    }

    /**
     * Resolve a translation key, returning null when no translation exists.
     */
    private function translate(?string $key): ?string
    {
        if ($key === null || $key === '') {
            return null;
        }

        $translated = __($key);

        return is_string($translated) && $translated !== $key ? $translated : null;
    }
}
