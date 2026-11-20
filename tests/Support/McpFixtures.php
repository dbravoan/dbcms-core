<?php

declare(strict_types=1);

namespace Tests\Support;

use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;

final class McpFixtures
{
    public static function identities(): void
    {
        PassportTestKeys::configure();
        CmsFixtures::localization();
        CmsFixtures::identities([
            'admin' => [],
            'author' => ['access:admin', 'manage:posts', 'use:ai'],
            'editor' => ['access:admin', 'manage:posts', 'manage:pages', 'use:ai'],
        ]);
        if (! Client::query()->where('grant_types', 'like', '%personal_access%')->exists()) {
            app(ClientRepository::class)->createPersonalAccessGrantClient('MCP fixture');
        }
    }
}
