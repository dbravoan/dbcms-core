<?php

declare(strict_types=1);

namespace DbCms\Core\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A block composing the intro of a curated filter page.
 *
 * Same shape as page/post/product sections, so the CMS block manager, the
 * section renderer and the theme styling all work unchanged.
 *
 * @property int $id
 * @property int $filter_page_id
 * @property string $type
 * @property int $order
 * @property array<string, mixed>|null $styling_json
 * @property bool $is_active
 * @property string $status
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read FilterPage $filterPage
 * @property-read Collection<int, FilterPageSectionTranslation> $translations
 */
class FilterPageSection extends Model
{
    protected $fillable = [
        'filter_page_id',
        'type',
        'order',
        'styling_json',
        'is_active',
        'status',
        'published_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'styling_json' => 'array',
            'is_active' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<FilterPage, $this>
     */
    public function filterPage(): BelongsTo
    {
        return $this->belongsTo(FilterPage::class);
    }

    /**
     * @return HasMany<FilterPageSectionTranslation, $this>
     */
    public function translations(): HasMany
    {
        return $this->hasMany(FilterPageSectionTranslation::class);
    }
}
