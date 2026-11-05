<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Content;

use DbCms\Core\Content\Contracts\EntitySchemaRegistryInterface;
use DbCms\Core\Content\EntitySchema;
use DbCms\Core\Content\Pages\Models\Page;
use DbCms\Core\Contracts\SectionContentVisibility;
use DbCms\Core\Contracts\Services\LocalizationServiceInterface;
use DbCms\Core\Contracts\Services\PublicContentDiscoveryInterface;
use DbCms\Core\Models\Market;
use DbCms\Core\Modules\ModuleManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/** Shared public visibility and URL contract for sitemap, feeds and semantic discovery. */
final class PublicContentDiscovery implements PublicContentDiscoveryInterface
{
    public function __construct(private readonly EntitySchemaRegistryInterface $entities, private readonly ModuleManager $modules, private readonly LocalizationServiceInterface $localization) {}

    /** @return array<string, EntitySchema> */
    public function schemas(): array
    {
        $schemas = [];
        foreach ($this->entities->all() as $schema) {
            $owner = $schema->ownerModule ?? $this->modules->ownerOfClass($schema->model);
            if ($schema->publicContent !== null && ($owner === null || $this->modules->isModuleEnabled($owner))) {
                $schemas[$schema->type] = $schema;
            }
        }

        return $schemas;
    }

    public function entityUrl(string $type, Model $entity, Market $market, string $locale): string
    {
        $schema = $this->entities->get($type) ?? throw new InvalidArgumentException('Unknown public content type.');
        $translation = $entity instanceof Page ? PublicTranslation::resolve($entity, $locale, $market) : $entity->getRelation('translations')->firstWhere('locale', $locale);
        $method = $schema->publicContent?->slugPathMethod;
        $slug = $method !== null ? (string) $entity->{$method}($locale) : (string) ($translation?->getAttribute('slug') ?? '');

        return $this->url($type, $market, $locale, $slug);
    }

    /**
     * @param  list<string>  $relations
     * @return Builder<Model>
     */
    public function query(string $type, Market $market, string $locale, array $relations = []): Builder
    {
        $schema = $this->entities->get($type) ?? throw new InvalidArgumentException('Unknown public content type.');
        /** @var class-string<Model> $class */
        $class = $schema->model;
        $model = new $class;
        // Mixed presentation modes are loaded individually by the section reader.
        $publicRelations = ! $model instanceof SectionContentVisibility && method_exists($model, 'publishedSections') ? ['publishedSections.translations'] : [];
        if ($model instanceof SectionContentVisibility) {
            $relations = array_values(array_filter($relations, static fn (string $relation): bool => $relation !== 'publishedSections' && ! str_starts_with($relation, 'publishedSections.')));
        }
        $query = $model->newQuery()->with(array_values(array_unique(['translations', 'markets', ...$publicRelations, ...$relations])));
        $owner = $schema->ownerModule ?? $this->modules->ownerOfClass($class);
        if ($schema->publicContent === null || ($owner !== null && ! $this->modules->isModuleEnabled($owner)) || ! $market->is_active || ! $market->languages()->where('code', $locale)->where('is_active', true)->exists()) {
            return $query->whereRaw('1 = 0');
        }
        if (method_exists($model, 'scopePublished')) {
            $query->scopes('published');
        } elseif (in_array('is_published', $model->getFillable(), true)) {
            $query->where('is_published', true);
        } elseif (in_array('is_active', $model->getFillable(), true)) {
            $query->where('is_active', true);
        } else {
            return $query->whereRaw('1 = 0');
        }
        if ($model instanceof Page) {
            $query->scopes(['forPublicTranslation' => [$market, $locale]]);
        } else {
            $query->whereHas('translations', fn (Builder $translation) => $translation->where('locale', $locale)->whereNotNull('slug')->where('slug', '!=', ''));
        }
        if (method_exists($model, 'scopeForMarket')) {
            // Respect module-owned availability, including Product market pivots.
            $query->scopes(['forMarket' => [$market]]);
        } else {
            $query->where(fn (Builder $visible) => $visible->whereHas('markets', fn (Builder $markets) => $markets->whereKey($market->getKey()))->orDoesntHave('markets'));
        }

        return $query;
    }

    /** @return Collection<int, Market> */
    public function markets(): Collection
    {
        return Market::query()->where('is_active', true)->with(['languages' => fn ($languages) => $languages->where('is_active', true)])->get();
    }

    public function url(string $type, Market $market, string $locale, string $slug): string
    {
        $schema = $this->entities->get($type) ?? throw new InvalidArgumentException('Unknown public content type.');
        $prefix = $schema->urlPrefix ?? $schema->searchPrefix ?? '';
        $prefix = $prefix !== '' ? $this->localization->getModulePrefix($prefix, $locale) : '';
        $path = $type === 'page' && $slug === 'home' ? '' : trim($prefix.'/'.$slug, '/');

        return $this->localization->url($path, $market, $locale);
    }
}
