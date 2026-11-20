<?php

declare(strict_types=1);

$features = [];
foreach (['mcp', 'captcha', 'maintenance', 'search'] as $feature) {
    $features[$feature] = json_decode((string) file_get_contents(__DIR__.'/../resources/settings/'.$feature.'.json'), true, 512, JSON_THROW_ON_ERROR);
}

return $features;
