<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Cms;

use DbCms\Core\Content\Contracts\EntitySchemaRegistryInterface;
use DbCms\Core\Modules\ModuleManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/** Time is a visibility input even when no content row is written at publication. */
final class PublicationBoundaryService
{
    private ?string $memoKey = null;

    /** @var array{fingerprint:string,nextSeconds:int|null}|null */
    private ?array $memo = null;

    /** @return array{fingerprint:string, nextSeconds:int|null} */
    public function state(): array
    {
        try {
            $version = (string) Cache::get(PageCacheService::INDEX_VERSION_KEY, 1);
        } catch (\Throwable) {
            $version = 'unavailable';
        }
        $key = now()->format('U').':'.$version;
        if ($this->memoKey === $key && $this->memo !== null) {
            return $this->memo;
        }
        $signature = [];
        $next = null;
        $modules = app(ModuleManager::class);
        foreach (app(EntitySchemaRegistryInterface::class)->all() as $schema) {
            $owner = $schema->ownerModule ?? $modules->ownerOfClass($schema->model);
            if ($owner !== null && ! $modules->isModuleEnabled($owner)) {
                continue;
            }
            $class = $schema->model;
            $model = new $class;
            if (! $model instanceof Model || ! in_array('published_at', $model->getFillable(), true) || ! in_array('is_published', $model->getFillable(), true)) {
                continue;
            }
            try {
                $query = $model->newQuery()->where('is_published', true)->whereNotNull('published_at');
                $signature[] = $class.':'.(string) (clone $query)->where('published_at', '<=', now())->max('published_at');
                $future = (clone $query)->where('published_at', '>', now())->min('published_at');
                if (is_string($future)) {
                    $seconds = max(1, (int) ceil(now()->diffInSeconds($future)));
                    $next = $next === null ? $seconds : min($next, $seconds);
                }
            } catch (\Throwable) {
                // Installation can inspect its cache before optional migrations exist.
            }
        }

        $this->memoKey = $key;

        return $this->memo = ['fingerprint' => sha1(implode('|', $signature)), 'nextSeconds' => $next];
    }
}
