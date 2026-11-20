<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Search;

use DbCms\Core\Contracts\Services\EntitySearchServiceInterface;
use DbCms\Core\Models\User;
use DbCms\Core\Modules\ModuleManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Whitelisted, locale-aware entity search powering the reusable
 * `<x-admin.search-select>` component. Sources and their columns come
 * exclusively from `config/entity_search.php`.
 */
class EntitySearchService implements EntitySearchServiceInterface
{
    /**
     * @return list<string>
     */
    public function sources(): array
    {
        return array_keys($this->definitions());
    }

    /**
     * @param  list<int|string>|null  $marketIds
     * @return array{results: list<array{value: string, label: string, icon: string, badge: string}>, has_more: bool}
     */
    public function search(
        string $source,
        ?string $query = null,
        ?string $valueKey = null,
        ?int $perPage = null,
        ?string $locale = null,
        ?array $marketIds = null,
    ): array {
        $definition = $this->definition($source);
        $this->authorizeSource($definition);
        $locale = $this->resolveLocale($locale);
        $valuePath = $this->resolveValuePath($definition, $valueKey);

        /** @var class-string<Model> $modelClass */
        $modelClass = $definition['model'];
        $builder = (new $modelClass)->newQuery();

        $this->applyRelations($builder, $definition);
        $this->applyFilters($builder, $definition);
        $this->applyMarketScope($builder, $definition, $marketIds);
        $this->applyScope($builder, $definition);
        $this->applySearch($builder, $definition, $query, $locale);
        $this->applyOrder($builder, $definition);

        $limit = $this->resolvePerPage($perPage);
        $rows = $builder->limit($limit + 1)->get();
        $hasMore = $rows->count() > $limit;

        $results = [];
        foreach ($rows->take($limit) as $row) {
            $mapped = $this->mapModel($row, $definition, $valuePath, $locale);
            if ($mapped !== null) {
                $results[] = $mapped;
            }
        }

        return ['results' => $results, 'has_more' => $hasMore];
    }

    /**
     * @param  list<string>  $values
     * @param  list<int|string>|null  $marketIds
     * @return list<array{value: string, label: string, icon: string, badge: string}>
     */
    public function resolve(
        string $source,
        array $values,
        ?string $valueKey = null,
        ?string $locale = null,
        ?array $marketIds = null,
    ): array {
        $values = array_values(array_filter(
            array_map('strval', $values),
            static fn (string $value): bool => $value !== ''
        ));

        if ($values === []) {
            return [];
        }

        $definition = $this->definition($source);
        $this->authorizeSource($definition);
        $locale = $this->resolveLocale($locale);
        $valuePath = $this->resolveValuePath($definition, $valueKey);

        /** @var class-string<Model> $modelClass */
        $modelClass = $definition['model'];
        $builder = (new $modelClass)->newQuery();

        $this->applyRelations($builder, $definition);
        $this->applyFilters($builder, $definition);
        $this->applyMarketScope($builder, $definition, $marketIds);
        $this->applyScope($builder, $definition);
        $this->applyValueMatch($builder, $valuePath, $values);

        $results = [];
        foreach ($builder->limit(count($values))->get() as $row) {
            $mapped = $this->mapModel($row, $definition, $valuePath, $locale);
            if ($mapped !== null) {
                $results[] = $mapped;
            }
        }

        return $results;
    }

    /** @param array<string, mixed> $definition */
    private function authorizeSource(array $definition): void
    {
        $id = Auth::guard('web')->id();
        $user = $id === null ? null : User::find($id);
        $permission = $definition['permission'] ?? null;
        abort_unless($user instanceof User && ! $user->is_service_account && is_string($permission)
            && ($user->isAdmin() || ($user->hasPermission('access:admin') && $user->hasPermission($permission))), 403);
        $module = $definition['module'] ?? null;
        abort_unless($module === null || (is_string($module) && app(ModuleManager::class)->isModuleEnabled($module)), 403);
    }

    /**
     * @return array<string, mixed>
     */
    private function definition(string $source): array
    {
        $definitions = $this->definitions();
        $definition = $definitions[$source] ?? null;

        if (! is_array($definition)) {
            throw new InvalidArgumentException("Unknown entity search source [{$source}].");
        }

        if (! isset($definition['model']) || ! is_string($definition['model'])) {
            throw new InvalidArgumentException("Invalid entity search source [{$source}].");
        }

        return $definition;
    }

    /**
     * @return array<string, mixed>
     */
    private function definitions(): array
    {
        $definitions = config('entity_search.sources');

        return is_array($definitions) ? $definitions : [];
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function resolveValuePath(array $definition, ?string $valueKey): string
    {
        $candidates = array_values(array_filter(
            (array) ($definition['value'] ?? ['id']),
            static fn (mixed $path): bool => is_string($path) && $path !== ''
        ));

        if ($candidates === []) {
            $candidates = ['id'];
        }

        if ($valueKey !== null && $valueKey !== '') {
            $allowed = array_map('strval', (array) ($definition['allowed_values'] ?? []));
            if (! in_array($valueKey, $allowed, true) && ! in_array($valueKey, $candidates, true)) {
                throw new InvalidArgumentException("Value key [{$valueKey}] is not allowed for this source.");
            }

            return $valueKey;
        }

        return (string) $candidates[0];
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  Builder<Model>  $builder
     */
    private function applyRelations(Builder $builder, array $definition): void
    {
        if ($this->usesTranslations($definition) && method_exists($builder->getModel(), 'translations')) {
            $builder->with('translations');
        }
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function usesTranslations(array $definition): bool
    {
        $paths = array_merge(
            (array) ($definition['value'] ?? []),
            (array) ($definition['label'] ?? []),
            (array) ($definition['search'] ?? []),
        );

        foreach ($paths as $path) {
            if (is_string($path) && str_starts_with($path, 'translation.')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  Builder<Model>  $builder
     */
    private function applyFilters(Builder $builder, array $definition): void
    {
        foreach ((array) ($definition['filters'] ?? []) as $column => $value) {
            $builder->where((string) $column, $value);
        }
    }

    /**
     * Restrict a market-aware source to the markets inherited from the block context.
     *
     * Sources opt in with `market_scoped => true` and must expose a `markets`
     * relation, mirroring the assignment pattern used by pages/posts/products.
     *
     * @param  array<string, mixed>  $definition
     * @param  list<int|string>|null  $marketIds
     * @param  Builder<Model>  $builder
     */
    private function applyMarketScope(Builder $builder, array $definition, ?array $marketIds): void
    {
        if (empty($definition['market_scoped']) || $marketIds === null) {
            return;
        }

        $ids = array_values(array_filter(
            array_map('intval', $marketIds),
            static fn (int $id): bool => $id > 0
        ));

        if ($ids === []) {
            return;
        }

        $builder->where(static function (Builder $query) use ($ids): void {
            $query->whereHas('markets', static fn (Builder $markets) => $markets->whereIn('markets.id', $ids))
                ->orDoesntHave('markets');
        });
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  Builder<Model>  $builder
     */
    private function applyScope(Builder $builder, array $definition): void
    {
        $scope = $definition['scope'] ?? null;
        if (! is_string($scope) || $scope === '') {
            return;
        }

        if (method_exists($builder->getModel(), 'scope'.Str::studly($scope))) {
            $builder->{$scope}();
        }
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  Builder<Model>  $builder
     */
    private function applySearch(Builder $builder, array $definition, ?string $query, string $locale): void
    {
        $term = $query !== null ? trim($query) : '';
        if ($term === '') {
            return;
        }

        $paths = array_values(array_filter(
            (array) ($definition['search'] ?? []),
            static fn (mixed $path): bool => is_string($path) && $path !== ''
        ));

        if ($paths === []) {
            return;
        }

        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%';

        $builder->where(function (Builder $nested) use ($paths, $like, $locale): void {
            foreach ($paths as $path) {
                if (str_starts_with((string) $path, 'translation.')) {
                    $field = substr((string) $path, strlen('translation.'));
                    $nested->orWhereHas('translations', function (Builder $translation) use ($field, $like, $locale): void {
                        $translation->where('locale', $locale)->where($field, 'like', $like);
                    });
                } else {
                    $nested->orWhere((string) $path, 'like', $like);
                }
            }
        });
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  Builder<Model>  $builder
     */
    private function applyOrder(Builder $builder, array $definition): void
    {
        $order = $definition['order'] ?? null;

        if (is_string($order) && $order !== '') {
            $builder->orderBy($order);

            return;
        }

        $builder->orderBy($builder->getModel()->getKeyName());
    }

    /**
     * @param  list<string>  $values
     * @param  Builder<Model>  $builder
     */
    private function applyValueMatch(Builder $builder, string $valuePath, array $values): void
    {
        if (str_starts_with($valuePath, 'translation.')) {
            $field = substr($valuePath, strlen('translation.'));
            $builder->whereHas('translations', static fn (Builder $query) => $query->whereIn($field, $values));

            return;
        }

        $builder->whereIn($valuePath, $values);
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array{value: string, label: string, icon: string, badge: string}|null
     */
    private function mapModel(Model $model, array $definition, string $valuePath, string $locale): ?array
    {
        $value = $this->resolvePath($model, $valuePath, $locale);
        if (! is_scalar($value) || (string) $value === '') {
            return null;
        }

        $label = null;
        foreach ((array) ($definition['label'] ?? []) as $path) {
            if (! is_string($path)) {
                continue;
            }

            $candidate = $this->resolvePath($model, $path, $locale);
            if (is_scalar($candidate) && trim((string) $candidate) !== '') {
                $label = (string) $candidate;
                break;
            }
        }

        return [
            'value' => (string) $value,
            'label' => $label ?? (string) $value,
            'icon' => (string) ($definition['icon'] ?? 'fa-solid fa-tag'),
            'badge' => (string) ($definition['badge'] ?? strtoupper($valuePath)),
        ];
    }

    private function resolvePath(Model $model, string $path, string $locale): mixed
    {
        if (str_starts_with($path, 'translation.')) {
            $field = substr($path, strlen('translation.'));
            $translations = $model->getRelationValue('translations');

            if (! $translations instanceof Collection) {
                return null;
            }

            $translation = $translations->firstWhere('locale', $locale) ?? $translations->first();

            return $translation instanceof Model ? $translation->getAttribute($field) : null;
        }

        return data_get($model, $path);
    }

    private function resolveLocale(?string $locale): string
    {
        return $locale ?? app()->getLocale();
    }

    private function resolvePerPage(?int $perPage): int
    {
        $default = (int) config('entity_search.per_page', 20);
        $max = (int) config('entity_search.max_per_page', 50);
        $resolved = $perPage ?? $default;

        return max(1, min($resolved, $max));
    }
}
