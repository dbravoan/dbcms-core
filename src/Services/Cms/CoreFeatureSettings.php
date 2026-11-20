<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Cms;

use DbCms\Core\Contracts\Services\GeneralSettingsServiceInterface;
use DbCms\Core\Contracts\Services\LocalizationServiceInterface;
use DbCms\Core\Services\Privacy\LegalPageService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Owns validated core feature configuration, including encrypted credentials. */
final class CoreFeatureSettings
{
    public function __construct(private readonly GeneralSettingsServiceInterface $settings) {}

    /** @return array<string, array<string, mixed>> */
    public function getSchema(string $feature): array
    {
        $schema = config('core_features.'.$feature.'.settings');
        if (! is_array($schema)) {
            throw ValidationException::withMessages(['feature' => 'Unknown core feature.']);
        }
        $expanded = [];
        foreach ($schema as $key => $field) {
            if (str_contains($key, '{locale}')) {
                try {
                    $locales = app(LocalizationServiceInterface::class)->activeLocales();
                } catch (QueryException) {
                    $locales = array_keys($field['defaults'] ?? ['es' => '']);
                }
                foreach ($locales as $locale) {
                    $locale = (string) $locale;
                    $copy = $field;
                    $copy['label'] = str_replace(['{language}', '{locale}'], [$locale, $locale], $field['label'] ?? $key);
                    $copy['default'] = $field['defaults'][$locale] ?? $field['default'] ?? '';
                    $expanded[str_replace('{locale}', $locale, $key)] = $copy;
                }
            } else {
                $expanded[$key] = $field;
            }
        }

        return $expanded;
    }

    public function get(string $feature, string $key, mixed $default = null): mixed
    {
        $field = $this->getSchema($feature)[$key] ?? [];
        $value = $this->settings->get($feature.'.'.$key, $field['default'] ?? $default);
        if (($field['secret'] ?? false) && is_string($value) && $value !== '') {
            return Crypt::decryptString($value);
        }

        return $value;
    }

    /** @return array<string, mixed> */
    public function getAll(string $feature): array
    {
        $values = [];
        foreach ($this->getSchema($feature) as $key => $field) {
            $values[$key] = $this->get($feature, $key);
        }

        return $values;
    }

    public function set(string $feature, string $key, mixed $value): void
    {
        $this->setMany($feature, [$key => $value]);
    }

    /** @param array<string, mixed> $values */
    public function setMany(string $feature, array $values): void
    {
        $schema = $this->getSchema($feature);
        if (array_diff(array_keys($values), array_keys($schema)) !== []) {
            throw ValidationException::withMessages(['settings' => 'Unknown core feature setting.']);
        }
        $rules = [];
        foreach ($values as $key => $value) {
            $field = $schema[$key];
            $rules[$key] = match ($field['type'] ?? 'text') {
                'boolean' => ['boolean'],
                'number' => ['required', 'numeric', 'min:'.($key === 'min_score' ? '0' : '1')],
                'select' => ['required', Rule::in(array_keys($field['options'] ?? []))],
                default => ['nullable', 'string', 'max:10000'],
            };
            if (str_starts_with($key, 'slug_')) {
                $rules[$key] = ['nullable', 'string', 'max:100', 'regex:/^[a-z0-9][a-z0-9_-]*$/i'];
            }
        }
        Validator::make($values, $rules)->validate();
        Validator::make($values, [
            'min_score' => ['sometimes', 'numeric', 'between:0,1'],
            'results_per_page' => ['sometimes', 'integer', 'between:1,100'],
            'min_results_to_index' => ['sometimes', 'integer', 'between:1,50'],
        ])->validate();
        foreach (['rate_limit_per_minute', 'results_per_page', 'min_results_to_index', 'retention_days', 'retry_after_fallback'] as $integer) {
            if (array_key_exists($integer, $values)) {
                Validator::make($values, [$integer => ['integer', 'min:1']])->validate();
                $values[$integer] = (int) $values[$integer];
            }
        }
        if ($feature === 'search') {
            $legal = app(LegalPageService::class);
            $policy = $legal->processing('search_terms');
            if (array_key_exists('retention_days', $values)) {
                $reviewedDays = (int) ($policy['retention_days'] ?? $this->get('search', 'retention_days', 90));
                if ((int) $values['retention_days'] !== $reviewedDays) {
                    throw ValidationException::withMessages(['retention_days' => 'Change search-term retention in the reviewed privacy configuration.']);
                }
            }
            if ($values['track_terms'] ?? $this->get('search', 'track_terms', false)) {
                $values['retention_days'] = (int) $legal->requireProcessing('search_terms')['retention_days'];
            }
        }
        $updates = [];
        foreach ($values as $key => $value) {
            $field = $schema[$key];
            if (($field['secret'] ?? false) && ($value === '' || $value === null)) {
                continue;
            }
            if (($field['type'] ?? '') === 'boolean') {
                $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            }
            $updates[$feature.'.'.$key] = ($field['secret'] ?? false) ? Crypt::encryptString((string) $value) : $value;
        }
        try {
            DB::transaction(fn () => $this->settings->setMany($updates, $feature));
        } finally {
            $this->settings->flushCache();
        }
    }
}
