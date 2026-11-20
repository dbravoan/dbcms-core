<?php

declare(strict_types=1);

namespace DbCms\Core\Forms\Models;

use DbCms\Core\Content\Contracts\TranslatableEntity;
use DbCms\Core\Models\Market;
use DbCms\Core\Services\Content\PublicTranslation;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $slug
 * @property string|null $notification_email
 * @property array<string,mixed>|null $layout
 * @property list<array<string,mixed>>|null $actions
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string $name
 * @property-read string|null $description
 * @property-read string $submit_button_text
 * @property-read string|null $success_message
 * @property-read Collection<int, CustomFormTranslation> $translations
 * @property-read Collection<int, FormField> $fields
 * @property-read Collection<int, FormSubmission> $submissions
 */
class CustomForm extends Model implements TranslatableEntity
{
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $fillable = [
        'slug',
        'notification_email',
        'is_active',
        'layout',
        'actions',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'layout' => 'array',
            'actions' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(static function (self $form): void {
            $form->submissions()->each(static fn (FormSubmission $submission) => $submission->delete());
        });
    }

    /** Load omitted definition columns without replacing supplied or dirty values. */
    public function loadDefinitionAttributes(): static
    {
        $missing = array_values(array_diff(['layout', 'actions', 'notification_email'], array_keys($this->getAttributes())));
        if (! $this->exists || $missing === []) {
            return $this;
        }
        $stored = $this->newQuery()->whereKey($this->getKey())->firstOrFail($missing);
        foreach ($missing as $attribute) {
            $this->setAttribute($attribute, $stored->getAttribute($attribute));
            $this->syncOriginalAttribute($attribute);
        }

        return $this;
    }

    /**
     * @return HasMany<CustomFormTranslation, $this>
     */
    public function translations(): HasMany
    {
        return $this->hasMany(CustomFormTranslation::class);
    }

    /**
     * @return BelongsToMany<Market, $this>
     */
    public function markets(): BelongsToMany
    {
        return $this->belongsToMany(Market::class, 'market_form');
    }

    /**
     * @return HasMany<FormField, $this>
     */
    public function fields(): HasMany
    {
        return $this->hasMany(FormField::class)->orderBy('order', 'asc');
    }

    /**
     * @return HasMany<FormSubmission, $this>
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(FormSubmission::class)->latest();
    }

    /**
     * Resolve the translation for the requested locale/market.
     *
     * A regional row may inherit blanks from the global row of the same
     * language. It never falls back to another language.
     */
    public function translation(?string $locale = null, ?int $marketId = null): ?CustomFormTranslation
    {
        $locale = $locale ?? app()->getLocale();
        $marketId = $marketId ?? self::currentMarketId();

        /** @var CustomFormTranslation|null $translation */
        $translation = PublicTranslation::resolveForMarketId($this, $locale, $marketId);

        return $translation;
    }

    /** @return Attribute<string, never> */
    protected function name(): Attribute
    {
        return Attribute::get(fn (): string => (string) ($this->translation()->name ?? $this->slug));
    }

    /** @return Attribute<string|null, never> */
    protected function description(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->translation()?->description);
    }

    /** @return Attribute<string, never> */
    protected function submitButtonText(): Attribute
    {
        return Attribute::get(fn (): string => (string) ($this->translation()->submit_button_text ?? 'Enviar'));
    }

    /** @return Attribute<string|null, never> */
    protected function successMessage(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->translation()?->success_message);
    }

    private static function currentMarketId(): ?int
    {
        if (! app()->bound('current.market')) {
            return null;
        }

        $market = app('current.market');

        return $market instanceof Market ? (int) $market->id : null;
    }
}
