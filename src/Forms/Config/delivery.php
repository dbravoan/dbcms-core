<?php

declare(strict_types=1);

return [
    // Server-owned receivers. Editors select an identifier; credentials never
    // enter form definitions, snapshots, submissions or capability payloads.
    // 'support' => ['url' => 'https://receiver.example/lead',
    //     'token' => env('FORMS_SUPPORT_TOKEN'), 'fields' => ['email', 'message'],
    //     'purpose' => null],
    // Each target requires reviewed legal processing forms_webhook_<identifier>.
    // Optional purposes use the shared consent receipt and are rechecked by jobs.
    'webhooks' => [],
];
