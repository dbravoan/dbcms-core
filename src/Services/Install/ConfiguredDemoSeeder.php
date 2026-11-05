<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Install;

use Illuminate\Database\Seeder;

final class ConfiguredDemoSeeder
{
    /** @return class-string<Seeder>|null */
    public static function resolve(): ?string
    {
        $configured = config('cms.demo_seeder');

        return is_string($configured) && is_subclass_of($configured, Seeder::class) ? $configured : null;
    }
}
