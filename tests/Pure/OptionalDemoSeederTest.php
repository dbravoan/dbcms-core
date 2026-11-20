<?php

declare(strict_types=1);

use DbCms\Core\Contracts\Services\InstallationEnvironmentServiceInterface;
use DbCms\Core\Database\Seeders\CmsFoundationSeeder;
use DbCms\Core\Database\Seeders\CompositionTemplateSeeder;
use DbCms\Core\Database\Seeders\DatabaseSeeder;
use DbCms\Core\Modules\ModuleManager;
use DbCms\Core\Services\Install\InstallationProvisioningService;
use DbCms\Core\Services\Security\AccessProvisioner;
use DbCms\Core\Services\Security\PassportKeyPermissionService;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Facade;
use Laravel\Passport\ClientRepository;

beforeEach(function (): void {
    $this->originalContainer = Container::getInstance();
    $this->originalFacadeApplication = Facade::getFacadeApplication();
    $this->originalArtisan = Facade::getFacadeApplication()?->bound(Kernel::class)
        ? Facade::getFacadeApplication()->make(Kernel::class)
        : null;
    $container = new Container;
    $container->instance('config', new Repository(['cms' => ['seed_demo' => false, 'demo_seeder' => 'Absent\\ProjectShowcaseSeeder']]));
    Container::setInstance($container);
    Facade::setFacadeApplication($container);
    Facade::clearResolvedInstance(Kernel::class);
    $this->demoContainer = $container;
});

afterEach(function (): void {
    Container::setInstance($this->originalContainer);
    Facade::setFacadeApplication($this->originalFacadeApplication);
    Facade::clearResolvedInstance(Kernel::class);
    if ($this->originalArtisan !== null) {
        Artisan::swap($this->originalArtisan);
    }
});

it('rejects unavailable or invalid requested demo before migrations and environment writes', function (mixed $seeder): void {
    config(['cms.demo_seeder' => $seeder]);
    $kernel = $this->createMock(Kernel::class);
    $kernel->expects($this->never())->method('call');
    $this->demoContainer->instance(Kernel::class, $kernel);
    $modules = $this->createMock(ModuleManager::class);
    $modules->expects($this->never())->method('installConfiguredModules');
    $environment = $this->createMock(InstallationEnvironmentServiceInterface::class);
    $environment->expects($this->never())->method('write');
    $clients = $this->createMock(ClientRepository::class);
    $clients->expects($this->never())->method('createPersonalAccessGrantClient');
    $service = new InstallationProvisioningService(new PassportKeyPermissionService, $clients, $environment, $modules, new AccessProvisioner($modules));

    $result = $service->provision(true);

    expect($result->success)->toBeFalse()
        ->and($result->message)->toContain('no incluye el contenido de demostración')
        ->and($result->logs)->toBe([]);
})->with(['absent class' => ['Absent\\ProjectShowcaseSeeder'], 'not a seeder' => [stdClass::class], 'disabled configuration' => [null]]);

it('rejects a requested missing demo before foundation seeding', function (): void {
    config(['cms.seed_demo' => true]);
    $seeder = new class extends DatabaseSeeder
    {
        public array $dispatched = [];

        public function call($class, $silent = false, array $parameters = [])
        {
            $this->dispatched[] = $class;

            return $this;
        }
    };

    expect(fn () => $seeder->run())->toThrow(DomainException::class, 'no incluye el contenido de demostración');
    expect($seeder->dispatched)->toBe([]);
});

it('seeds only foundation when demo is not requested even if the showcase is absent', function (): void {
    $seeder = new class extends DatabaseSeeder
    {
        public array $dispatched = [];

        public function call($class, $silent = false, array $parameters = [])
        {
            $this->dispatched[] = $class;

            return $this;
        }
    };
    $seeder->run();

    expect($seeder->dispatched)->toBe([CmsFoundationSeeder::class, CompositionTemplateSeeder::class]);
});

it('dispatches an available configured demo after foundation when explicitly requested', function (): void {
    $demo = new class extends Seeder
    {
        public function run(): void {}
    };
    config(['cms.seed_demo' => true, 'cms.demo_seeder' => $demo::class]);
    $seeder = new class extends DatabaseSeeder
    {
        public array $dispatched = [];

        public function call($class, $silent = false, array $parameters = [])
        {
            $this->dispatched[] = $class;

            return $this;
        }
    };
    $seeder->run();

    expect($seeder->dispatched)->toBe([CmsFoundationSeeder::class, $demo::class, CompositionTemplateSeeder::class]);
});
