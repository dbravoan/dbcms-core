<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use DbCms\Core\Capabilities\ActorContext;
use DbCms\Core\Capabilities\AvailableCapabilities;
use DbCms\Core\Capabilities\CallableCapability;
use DbCms\Core\Capabilities\CapabilityAuthorizer;
use DbCms\Core\Capabilities\CapabilityRegistry;
use DbCms\Core\Capabilities\Contracts\CapabilityPresentationInterface;
use DbCms\Core\Http\Middleware\EnsureModuleEnabled;
use DbCms\Core\Modules\ModuleManager;
use Illuminate\Container\Container;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ModuleCapabilityAvailabilityTest extends TestCase
{
    /** @return list<array{string}> */
    public static function moduleOwners(): array
    {
        return [['mcp'], ['ai_assistant'], ['maintenance']];
    }

    #[DataProvider('moduleOwners')]
    public function test_disabled_owner_is_never_advertised_or_dispatched(string $owner): void
    {
        $modules = $this->createMock(ModuleManager::class);
        $modules->method('isModuleEnabled')->with($owner)->willReturn(false);
        $authorizer = $this->createMock(CapabilityAuthorizer::class);
        $authorizer->expects(self::never())->method('allowed');
        $presentation = $this->createMock(CapabilityPresentationInterface::class);
        $presentation->expects(self::never())->method('schema');
        $registry = new CapabilityRegistry;
        $registry->register(new CallableCapability('audit_disabled_owner', 'Disabled owner', [], [], null, 'read', static fn (): array => []), $owner);
        $available = new AvailableCapabilities($registry, $authorizer, $modules, $presentation);
        $actor = new ActorContext(1, 'web');

        self::assertSame([], $available->manifest($actor));
        self::assertSame('Módulo no disponible.', $available->reason('audit_disabled_owner', $actor));
    }

    #[DataProvider('moduleOwners')]
    public function test_disabled_module_middleware_blocks_before_the_route_runs(string $owner): void
    {
        $modules = $this->createMock(ModuleManager::class);
        $modules->method('isModuleEnabled')->with($owner)->willReturn(false);
        $factory = $this->createMock(ResponseFactory::class);
        $factory->expects(self::once())->method('json')
            ->with(['error' => "El módulo '{$owner}' se encuentra actualmente desactivado."], 404)
            ->willReturn(new JsonResponse([], 404));
        $previous = Container::getInstance();
        $isolated = new Container;
        $isolated->instance(ResponseFactory::class, $factory);
        Container::setInstance($isolated);
        try {
            $request = Request::create('/api/module-audit', 'POST', server: ['HTTP_ACCEPT' => 'application/json']);
            $response = (new EnsureModuleEnabled($modules))->handle($request, static function (): never {
                throw new \LogicException('A disabled module must never reach its route.');
            }, $owner);
            self::assertSame(404, $response->getStatusCode());
        } finally {
            Container::setInstance($previous);
        }
    }
}
