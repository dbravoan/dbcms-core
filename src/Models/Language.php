<?php

declare(strict_types=1);

namespace DbCms\Core\Models;

use DbCms\Core\Contracts\Services\LocalizationServiceInterface;
use DbCms\Core\Localization\FlagCatalog;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $native_name
 * @property-read string $flag
 * @property string $direction
 * @property bool $is_default
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Language extends Model
{
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    /**
     * Any write to the language table makes the memoized active-language list
     * stale, so the next read reflects the change (reactive backoffice).
     */
    protected static function booted(): void
    {
        static::saved(static fn () => app(LocalizationServiceInterface::class)->forgetCache());
        static::deleted(static fn () => app(LocalizationServiceInterface::class)->forgetCache());
    }

    protected $fillable = [
        'code',
        'name',
        'native_name',
        'flag',
        'direction',
        'is_default',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Resolve the flag emoji assigned in the backoffice, or a fallback derived
     * from the language code (explicit language map, then country derivation,
     * then the globe).
     */
    /** @return Attribute<string, never> */
    protected function flag(): Attribute
    {
        return Attribute::get(function (): string {
            $attributes = $this->getAttributes();
            $flag = trim((string) ($attributes['flag'] ?? ''));

            return $flag !== '' ? $flag : FlagCatalog::forLanguage((string) ($attributes['code'] ?? ''));
        });
    }

    /**
     * @return BelongsToMany<Market, $this>
     */
    public function markets(): BelongsToMany
    {
        return $this->belongsToMany(Market::class, 'market_language')
            ->withPivot('is_default')
            ->withTimestamps();
    }
}
