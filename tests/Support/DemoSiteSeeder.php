<?php

declare(strict_types=1);

namespace Tests\Support;

use DbCms\Core\Content\Pages\Models\Page;
use DbCms\Core\Database\Seeders\CmsFoundationSeeder;
use Illuminate\Database\Seeder;

final class DemoSiteSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(CmsFoundationSeeder::class);
        $page = Page::create(['is_published' => true]);
        $page->translations()->create(['locale' => 'es', 'title' => 'Fixture demonstration', 'slug' => 'fixture-demonstration']);
    }
}
