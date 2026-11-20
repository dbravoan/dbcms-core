<?php

declare(strict_types=1);

namespace DbCms\Core\Forms\Models;

use DbCms\Core\Models\Market;
use DbCms\Core\Services\Content\PublicTranslation;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $custom_form_id
 * @property string $field_key
 * @property string $type
 * @property bool $is_required
 * @property string|null $validation_rules
 * @property int $order
 * @property-read string $label
 * @property-read string|null $placeholder
 * @property-read array<string, mixed>|null $options
 * @property-read Collection<int, FormFieldTranslation> $translations
 */
class FormField extends Model
{
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $fillable = [
        'custom_form_id',
        'field_key',
        'type',
        'is_required',
        'validation_rules',
        'order',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
        ];
    }

    /**
     * @return HasMany<FormFieldTranslation, $this>
     */
    public function translations(): HasMany
    {
        return $this->hasMany(FormFieldTranslation::class);
    }

    /**
     * @return BelongsTo<CustomForm, $this>
     */
    public function form(): BelongsTo
    {
        return $this->belongsTo(CustomForm::class, 'custom_form_id');
    }

    public function translation(?string $locale = null, ?int $marketId = null): ?FormFieldTranslation
    {
        $locale = $locale ?? app()->getLocale();
        $marketId = $marketId ?? $this->currentMarketId();

        /** @var FormFieldTranslation|null $translation */
        $translation = PublicTranslation::resolveForMarketId($this, $locale, $marketId);

        return $translation;
    }

    /** @return Attribute<string, never> */
    protected function label(): Attribute
    {
        return Attribute::get(fn (): string => (string) ($this->translation()->label ?? $this->field_key));
    }

    /** @return Attribute<string|null, never> */
    protected function placeholder(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->translation()?->placeholder);
    }

    /**
     * @return Attribute<array<string, mixed>|null, never>
     */
    protected function options(): Attribute
    {
        return Attribute::get(fn (): ?array => $this->translation()?->options);
    }

    private function currentMarketId(): ?int
    {
        if (! app()->bound('current.market')) {
            return null;
        }

        $market = app('current.market');

        return $market instanceof Market ? (int) $market->id : null;
    }
}
