<?php

declare(strict_types=1);

namespace DbCms\Core\Models;

use DbCms\Core\Contracts\Sectionable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Admin-curated content for a dynamic filter page.
 *
 * Keyed by module + market + locale + filter signature. When present it
 * overrides the runtime-composed text, may compose a rich intro with the CMS
 * block manager (same sections shape as pages/posts/products) and — if
 * `is_indexable` and the page passes the quality gate — makes the combination
 * indexable.
 *
 * @property int $id
 * @property string $module
 * @property string|null $market_code
 * @property string $locale
 * @property string $signature
 * @property string|null $path
 * @property string|null $title
 * @property string|null $heading
 * @property string|null $meta_description
 * @property bool $is_indexable
 */
class FilterPage extends Model implements Sectionable
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'module',
        'market_code',
        'locale',
        'signature',
        'path',
        'title',
        'heading',
        'meta_description',
        'is_indexable',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_indexable' => 'boolean',
        ];
    }

    /**
     * Intro blocks (block manager), rendered in the front above the results.
     *
     * @return HasMany<FilterPageSection, $this>
     */
    public function sections(): HasMany
    {
        return $this->hasMany(FilterPageSection::class)->orderBy('order');
    }

    public function sectionModelClass(): string
    {
        return FilterPageSection::class;
    }

    public function sectionTranslationModelClass(): string
    {
        return FilterPageSectionTranslation::class;
    }

    public function sectionForeignKey(): string
    {
        return 'filter_page_id';
    }
}
