<?php

declare(strict_types=1);

namespace DbCms\Core\Content\Pages\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $page_id
 * @property string $type
 * @property int $order
 * @property array<string, mixed>|null $styling_json
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Page $page
 * @property-read Collection<int, SectionTranslation> $translations
 */
class PageSection extends Model
{
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $fillable = [
        'page_id',
        'type',
        'order',
        'styling_json',
        'is_active',
        'status',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'styling_json' => 'array',
            'is_active' => 'boolean',
            'order' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Page, $this>
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    /**
     * @return HasMany<SectionTranslation, $this>
     */
    public function translations(): HasMany
    {
        return $this->hasMany(SectionTranslation::class);
    }
}
