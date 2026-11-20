<?php

declare(strict_types=1);

namespace DbCms\Core\Content\Pages\Models;

use DbCms\Core\Content\Contracts\TranslatableEntity;
use DbCms\Core\Content\Pages\Services\PageViewRegistry;
use DbCms\Core\Contracts\Sectionable;
use DbCms\Core\Contracts\SectionContentVisibility;
use DbCms\Core\Models\Market;
use DbCms\Core\Traits\MarketScoped;
use DbCms\Core\Traits\Translatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $template
 * @property string $layout
 * @property bool $is_published
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, PageTranslation> $translations
 * @property-read Collection<int, PageSection> $sections
 */
class Page extends Model implements Sectionable, SectionContentVisibility, TranslatableEntity
{
    /** @use HasFactory<Factory<static>> */
    use HasFactory, MarketScoped, Translatable;

    protected $fillable = [
        'template',
        'layout',
        'is_published',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Publishing always stamps a publish date: the front resolves pages
        // through `published()` which requires is_published AND published_at.
        static::saving(function (Page $page): void {
            if ($page->is_published && $page->published_at === null) {
                $page->published_at = now();
            }
        });
    }

    /**
     * @return HasMany<PageTranslation, $this>
     */
    public function translations(): HasMany
    {
        return $this->hasMany(PageTranslation::class);
    }

    /**
     * @return HasMany<PageSection, $this>
     */
    public function sections(): HasMany
    {
        return $this->hasMany(PageSection::class)->where('is_active', true)->orderBy('order', 'asc');
    }

    public function sectionModelClass(): string
    {
        return PageSection::class;
    }

    public function sectionTranslationModelClass(): string
    {
        return SectionTranslation::class;
    }

    public function sectionForeignKey(): string
    {
        return 'page_id';
    }

    public function usesSectionContent(): bool
    {
        return ! PageViewRegistry::isBlade((string) $this->template);
    }

    /**
     * @return HasMany<PageSection, $this>
     */
    public function publishedSections(): HasMany
    {
        return $this->hasMany(PageSection::class)
            ->where('is_active', true)
            ->where('status', 'published')
            ->orderBy('order', 'asc');
    }

    /**
     * @return BelongsToMany<Market, $this>
     */
    public function markets(): BelongsToMany
    {
        return $this->belongsToMany(Market::class, 'market_page');
    }

    /**
     * @param  Builder<Page>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true)->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    /** @param Builder<Page> $query */
    public function scopeForPublicTranslation(Builder $query, ?Market $market, string $locale, ?string $slug = null): void
    {
        foreach (['slug', 'title'] as $field) {
            $value = $field === 'slug' ? $slug : null;
            $query->where(function (Builder $eligible) use ($market, $locale, $field, $value): void {
                $nonblank = static fn (Builder $translation) => $translation->where('locale', $locale)->whereNotNull($field)->whereRaw('TRIM('.$field.') != ?', ['']);
                if ($market !== null) {
                    $eligible->whereHas('translations', function (Builder $translation) use ($nonblank, $market, $field, $value): void {
                        $nonblank($translation)->where('market_id', $market->id);
                        if ($value !== null) {
                            $translation->where($field, $value);
                        }
                    });
                }
                $eligible->orWhere(function (Builder $fallback) use ($nonblank, $market, $field, $value): void {
                    if ($market !== null) {
                        $fallback->whereDoesntHave('translations', fn (Builder $translation) => $nonblank($translation)->where('market_id', $market->id));
                    }
                    $fallback->whereHas('translations', function (Builder $translation) use ($nonblank, $field, $value): void {
                        $nonblank($translation)->whereNull('market_id');
                        if ($value !== null) {
                            $translation->where($field, $value);
                        }
                    });
                });
            });
        }
    }
}
