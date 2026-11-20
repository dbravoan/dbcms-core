<?php

declare(strict_types=1);

return [
    'email_enabled' => env('MODULE_NOTIFICATION_EMAIL_ENABLED', true),
    'broadcast_roles' => ['admin', 'super-admin'],
    'module_permissions' => [
        'forms' => 'manage:forms',
        'shop' => 'manage:shop',
        'blog' => 'manage:posts',
        'ai_assistant' => 'use:ai',
    ],
];
