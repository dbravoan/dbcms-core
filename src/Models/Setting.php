<?php

declare(strict_types=1);

namespace DbCms\Core\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $group
 * @property string $key
 * @property string|null $value
 * @property string $type
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Setting extends Model
{
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $fillable = [
        'group',
        'key',
        'value',
        'type',
    ];

    /**
     * Cast raw string value to native PHP type based on 'type' attribute.
     */
    public function getCastedValue(): mixed
    {
        if ($this->value === null) {
            return null;
        }

        return match ($this->type) {
            'boolean', 'bool' => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            'number', 'integer', 'int' => str_contains($this->value, '.') ? (float) $this->value : (int) $this->value,
            'json', 'array' => json_decode($this->value, true),
            default => $this->value,
        };
    }

    /**
     * Helper to retrieve casted value by key.
     */
    public static function getValue(string $key, mixed $default = null): mixed
    {
        /** @var self|null $setting */
        $setting = static::where('key', $key)->first();

        return $setting !== null ? $setting->getCastedValue() : $default;
    }

    /**
     * Helper to store or update a setting.
     */
    public static function setValue(string $key, mixed $value, string $group = 'general', string $type = 'string'): self
    {
        $serialized = match ($type) {
            'boolean', 'bool' => $value ? '1' : '0',
            'json', 'array' => is_string($value) ? $value : json_encode($value),
            default => (string) $value,
        };

        /** @var self $setting */
        $setting = static::updateOrCreate(
            ['key' => $key],
            [
                'group' => $group,
                'value' => $serialized,
                'type' => $type,
            ]
        );

        return $setting;
    }
}
