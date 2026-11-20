<?php

declare(strict_types=1);

namespace Database\Seeders;

use DbCms\Core\Database\Seeders\DatabaseSeeder as CmsDatabaseSeeder;
use Illuminate\Database\Seeder;

/** Laravel's conventional entry point; project seed data can follow the CMS. */
final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(CmsDatabaseSeeder::class);
    }
}
