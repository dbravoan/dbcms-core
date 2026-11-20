<?php

declare(strict_types=1);

namespace Tests\Feature;

use DbCms\Core\Blocks\Contracts\BlockRegistryInterface;
use DbCms\Core\Models\User;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class CmsIntegrationTest extends TestCase
{
    public function test_the_application_loads_the_cms_without_optional_packages(): void
    {
        $registry = app(BlockRegistryInterface::class);
        $this->assertTrue($registry->isAvailable('heading'));
        $this->assertStringContainsString('Contenido de prueba', $registry->render('heading', ['text' => 'Contenido de prueba']));
        $this->assertSame(User::class, config('auth.providers.users.model'));
        $this->assertTrue(Route::has('install.step1'));
        $this->assertTrue(Route::has('admin.login'));
        $this->assertTrue(view()->exists('cms-core::admin.layout'));
    }
}
