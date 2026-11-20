<?php

declare(strict_types=1);

use DbCms\Core\DTOs\Auth\RegisterUserDTO;
use DbCms\Core\Enums\OAuthScope;

test('RegisterUserDTO creates immutable typed instance from array', function (): void {
    $dto = RegisterUserDTO::fromArray([
        'name' => 'Agent 007',
        'email' => 'bond@agentic.ai',
        'password' => 'LicenseToCode',
        'scopes' => ['read:content', 'write:content'],
    ]);

    expect($dto->name)->toBe('Agent 007')
        ->and($dto->email)->toBe('bond@agentic.ai')
        ->and($dto->password)->toBe('LicenseToCode')
        ->and($dto->scopes)->toHaveCount(2)
        ->and($dto->scopes[0])->toBe(OAuthScope::READ_CONTENT)
        ->and($dto->scopes[1])->toBe(OAuthScope::WRITE_CONTENT);
});
