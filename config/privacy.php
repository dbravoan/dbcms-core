<?php

declare(strict_types=1);

return [
    // Collection and retention policy lives in the reviewed legal.processing settings.
    'external_services' => [],
    // Necessary media/CDN hosts require a reviewed assets_<id> processing entry.
    'asset_services' => [],
    'forms' => ['collect_network_metadata' => false],
];
