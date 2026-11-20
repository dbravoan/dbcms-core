<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Auth;

use DbCms\Core\Models\User;

final class DemoPersonaAccess
{
    /** @var array<string, string> */
    public const PERSONAS = [
        'admin' => 'admin@db-cms.io',
        'editor' => 'editor@db-cms.io',
        'author' => 'author@db-cms.io',
        'marketing' => 'marketing@db-cms.io',
        'viewer' => 'viewer@db-cms.io',
    ];

    public function enabled(): bool
    {
        return app()->environment(['local', 'testing']) && (bool) config('cms.quick_login', false);
    }

    /** @return array<string, string> role => existing account email */
    public function availablePersonas(): array
    {
        if (! $this->enabled()) {
            return [];
        }

        $emails = User::query()->whereIn('email', self::PERSONAS)->pluck('email')->all();

        return array_filter(self::PERSONAS, static fn (string $email): bool => in_array($email, $emails, true));
    }
}
