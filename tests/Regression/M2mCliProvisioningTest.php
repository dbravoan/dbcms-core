<?php

declare(strict_types=1);

use DbCms\Core\Console\Commands\CreateM2mClientCommand;
use DbCms\Core\Services\Auth\ServiceAccountProvisioningService;

it('requires an explicit CMS role and delegates M2M creation to the shared provisioning service', function (): void {
    $command = new CreateM2mClientCommand;
    expect($command->getDefinition()->hasOption('role'))->toBeTrue();
    $parameters = (new ReflectionMethod($command, 'handle'))->getParameters();
    expect($parameters)->toHaveCount(1)
        ->and((string) $parameters[0]->getType())->toBe(ServiceAccountProvisioningService::class);
});
