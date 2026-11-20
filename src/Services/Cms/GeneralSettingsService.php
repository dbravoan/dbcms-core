<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Cms;

use DbCms\Core\Contracts\Services\GeneralSettingsServiceInterface;
use DbCms\Core\Models\Setting;
use Illuminate\Support\Facades\Config;

class GeneralSettingsService implements GeneralSettingsServiceInterface
{
    /**
     * In-memory cache of resolved settings.
     *
     * @var array<string, mixed>|null
     */
    protected ?array $cache = null;

    /**
     * {@inheritdoc}
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $this->ensureLoaded();

        if ($this->cache !== null && array_key_exists($key, $this->cache)) {
            $val = $this->cache[$key];
            // An explicit empty string is a real value: clearing a nullable
            // setting must not resurrect the config/default fallback.
            if ($val !== null) {
                return $val;
            }
        }

        // Check config fallback for core parameters
        $configFallback = match ($key) {
            'general.site_name' => Config::get('cms.name'),
            'general.site_description' => Config::get('seo.defaults.description'),
            default => null,
        };

        if ($configFallback !== null && $configFallback !== '') {
            return $configFallback;
        }

        return $default;
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $key, mixed $value, string $group = 'general', string $type = 'string'): void
    {
        Setting::setValue($key, $value, $group, $type);

        if ($this->cache !== null) {
            $this->cache[$key] = $value;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function setMany(array $settings, string $group = 'general'): void
    {
        foreach ($settings as $key => $value) {
            $type = 'string';
            if (is_bool($value)) {
                $type = 'boolean';
            } elseif (is_int($value) || is_float($value)) {
                $type = 'number';
            } elseif (is_array($value)) {
                $type = 'json';
            } elseif (is_string($value) && strlen($value) > 255) {
                $type = 'text';
            }

            Setting::setValue($key, $value, $group, $type);

            if ($this->cache !== null) {
                $this->cache[$key] = $value;
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function allGrouped(): array
    {
        $grouped = [];
        $settings = Setting::all();

        foreach ($settings as $setting) {
            $grouped[$setting->group][$setting->key] = $setting->getCastedValue();
        }

        return $grouped;
    }

    /**
     * {@inheritdoc}
     */
    public function flushCache(): void
    {
        $this->cache = null;
    }

    /**
     * Load all settings into in-memory array once per lifecycle.
     */
    protected function ensureLoaded(): void
    {
        if ($this->cache !== null) {
            return;
        }

        $this->cache = [];
        try {
            $records = Setting::all();
            foreach ($records as $record) {
                $this->cache[$record->key] = $record->getCastedValue();
            }
        } catch (\Throwable) {
            // Table might not exist during early migrations
        }
    }
}
