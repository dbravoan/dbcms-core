<?php

declare(strict_types=1);

namespace DbCms\Core\Contracts\Services;

use DbCms\Core\Content\EntitySchema;
use DbCms\Core\Models\Market;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

interface PublicContentDiscoveryInterface
{
    /** @return array<string, EntitySchema> */
    public function schemas(): array;

    public function entityUrl(string $type, Model $entity, Market $market, string $locale): string;

    /**
     * @param  list<string>  $relations
     * @return Builder<Model>
     */
    public function query(string $type, Market $market, string $locale, array $relations = []): Builder;

    /** @return Collection<int, Market> */
    public function markets(): Collection;

    public function url(string $type, Market $market, string $locale, string $slug): string;
}
