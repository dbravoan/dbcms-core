<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Cms;

use DbCms\Core\Content\Contracts\EntitySchemaRegistryInterface;
use DbCms\Core\Modules\ModuleManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/** Time is a visibility input even when no content row is written at publication. */
final class PublicationBoundaryService
{
    /** @return array{fingerprint:string, nextSeconds:int|null} */
    public function state(?int $version = null): array
    {
        $now = now();
        $timestamp = $now->getTimestamp();
        $key = 'cms:publication-boundary:'.($version ?? app(PageCacheService::class)->indexVersion());
        $cacheAvailable = true;
        try {
            $cached = Cache::get($key);
            if (is_array($cached) && is_string($cached['fingerprint'] ?? null)
                && is_int($cached['computed_at'] ?? null) && $cached['computed_at'] <= $timestamp
                && array_key_exists('next_at', $cached) && ($cached['next_at'] === null || (is_int($cached['next_at']) && $timestamp < $cached['next_at']))) {
                return [
                    'fingerprint' => $cached['fingerprint'],
                    'nextSeconds' => $cached['next_at'] === null ? null : $cached['next_at'] - $timestamp,
                ];
            }
        } catch (\Throwable) {
            // Public reads can still determine their visibility when the cache store fails.
            $cacheAvailable = false;
        }

        $signature = [];
        $nextAt = null;
        $complete = true;
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
                $signature[] = $class.':'.(string) (clone $query)->where('published_at', '<=', $now)->max('published_at');
                $future = (clone $query)->where('published_at', '>', $now)->min('published_at');
                if (is_string($future)) {
                    $boundary = Carbon::parse($future)->getTimestamp();
                    $nextAt = $nextAt === null ? $boundary : min($nextAt, $boundary);
                }
            } catch (\Throwable) {
                // Installation can inspect its cache before optional migrations exist.
                $complete = false;
            }
        }

        $fingerprint = sha1(implode('|', $signature));
        $seconds = $nextAt === null ? null : max(1, $nextAt - $timestamp);

        if ($complete && $cacheAvailable) {
            try {
                Cache::put($key, ['fingerprint' => $fingerprint, 'computed_at' => $timestamp, 'next_at' => $nextAt], min($seconds ?? 300, 300));
            } catch (\Throwable) {
                // The calculated boundary is usable without cache storage.
            }
        }

        return ['fingerprint' => $fingerprint, 'nextSeconds' => $seconds];
    }
}
