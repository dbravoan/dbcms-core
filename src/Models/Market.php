<?php

declare(strict_types=1);

namespace DbCms\Core\Models;

use DbCms\Core\Contracts\Services\LocalizationServiceInterface;
use DbCms\Core\Localization\FlagCatalog;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $currency
 * @property string $default_locale
 * @property int|null $fallback_market_id
 * @property bool $is_default
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string $flag
 * @property-read Market|null $fallbackMarket
 */
class Market extends Model
{
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    /**
     * Same contract as {@see Language}: writing a market invalidates the
     * memoized active-market list so the backoffice stays reactive.
     */
    protected static function booted(): void
    {
        static::saved(static fn () => app(LocalizationServiceInterface::class)->forgetCache());
        static::deleted(static fn () => app(LocalizationServiceInterface::class)->forgetCache());
    }

    protected $fillable = [
        'code',
        'name',
        'flag',
        'currency',
        'default_locale',
        'fallback_market_id',
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
     * from the market code (globe for international / unknown markets).
     */
    /** @return Attribute<string, never> */
    protected function flag(): Attribute
    {
        return Attribute::get(function (): string {
            $attributes = $this->getAttributes();
            $flag = trim((string) ($attributes['flag'] ?? ''));

            return $flag !== '' ? $flag : FlagCatalog::forCode((string) ($attributes['code'] ?? ''));
        });
    }

    /**
     * @return BelongsTo<Market, $this>
     */
    public function fallbackMarket(): BelongsTo
    {
        return $this->belongsTo(Market::class, 'fallback_market_id');
    }

    /**
     * @return BelongsToMany<Language, $this>
     */
    public function languages(): BelongsToMany
    {
        return $this->belongsToMany(Language::class, 'market_language')
            ->withPivot('is_default')
            ->withTimestamps();
    }
}
