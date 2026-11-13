<?php

declare(strict_types=1);

namespace DbCms\Core\Models;

use DbCms\Core\Content\Contracts\TranslatableEntity;
use DbCms\Core\Contracts\Sectionable;
use DbCms\Core\Traits\MarketScoped;
use DbCms\Core\Traits\Translatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property bool $is_active
 * @property bool $is_published
 * @property array<string,array{node_id:string,field:string,label?:string}> $exposed_properties
 * @property Carbon|null $published_at
 * @property-read Collection<int,BlockComponentTranslation> $translations
 * @property-read Collection<int,BlockComponentSection> $sections
 */
class BlockComponent extends Model implements Sectionable, TranslatableEntity
{
    use MarketScoped, Translatable;

    protected $fillable = ['is_active', 'is_published', 'exposed_properties', 'published_at'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_published' => 'boolean', 'exposed_properties' => 'array', 'published_at' => 'datetime'];
    }

    /**
     * @return HasMany<BlockComponentTranslation,$this> */
    public function translations(): HasMany
    {
        return $this->hasMany(BlockComponentTranslation::class);
    }

    /**
     * @return HasMany<BlockComponentSection,$this> */
    public function sections(): HasMany
    {
        return $this->hasMany(BlockComponentSection::class)->orderBy('order')->orderBy('id');
    }

    /**
     * @return HasMany<BlockComponentSection,$this> */
    public function publishedSections(): HasMany
    {
        return $this->sections()->where('is_active', true)->where('status', 'published');
    }

    /**
     * @return BelongsToMany<Market,$this> */
    public function markets(): BelongsToMany
    {
        return $this->belongsToMany(Market::class, 'block_component_market');
    }

    public function sectionModelClass(): string
    {
        return BlockComponentSection::class;
    }

    public function sectionTranslationModelClass(): string
    {
        return BlockComponentSectionTranslation::class;
    }

    public function sectionForeignKey(): string
    {
        return 'block_component_id';
    }

    /** @param Builder<BlockComponent> $query */
    public function scopePublished(Builder $query): void
    {
        $query->where('is_active', true)->where('is_published', true)->whereNotNull('published_at')->where('published_at', '<=', now());
    }
}
