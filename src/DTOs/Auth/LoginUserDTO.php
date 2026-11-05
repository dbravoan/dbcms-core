<?php

declare(strict_types=1);

namespace DbCms\Core\DTOs\Auth;

use DbCms\Core\DTOs\BaseDTO;

readonly class LoginUserDTO extends BaseDTO
{
    /**
     * @param  list<string>  $scopes
     */
    public function __construct(
        public string $email,
        public string $password,
        public string $deviceName = 'api-client',
        public array $scopes = ['read:content']
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        /** @var list<string> $scopes */
        $scopes = (array) ($data['scopes'] ?? ['read:content']);

        return new self(
            email: (string) $data['email'],
            password: (string) $data['password'],
            deviceName: (string) ($data['device_name'] ?? 'api-client'),
            scopes: $scopes
        );
    }
}
