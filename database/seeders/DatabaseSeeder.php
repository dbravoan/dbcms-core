<?php

declare(strict_types=1);

namespace DbCms\Core\Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $demoSeeder = null;
        if (filter_var(config('cms.seed_demo', false), FILTER_VALIDATE_BOOLEAN)) {
            $configured = config('cms.demo_seeder');
            if (! is_string($configured) || ! is_subclass_of($configured, Seeder::class)) {
                throw new \DomainException('Este proyecto no incluye el contenido de demostración. Desactiva CMS_SEED_DEMO.');
            }
            $demoSeeder = $configured;
        }
        $this->call(CmsFoundationSeeder::class);
        if ($demoSeeder !== null) {
            $this->call($demoSeeder);
        }
    }
}
