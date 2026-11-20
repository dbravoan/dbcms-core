<?php

declare(strict_types=1);

use DbCms\Core\Cms;

$classMap = require base_path('vendor/composer/autoload_classmap.php');
foreach (['App\\Models\\User', 'Database\\Factories\\UserFactory'] as $retired) {
    if (isset($classMap[$retired])) {
        throw new RuntimeException('The application class map still references retired Laravel examples: '.$retired);
    }
}
foreach (['CORE_AND_EXTENSIONS.md', 'CONVENTIONS.md', 'EXTENSIONS.md'] as $document) {
    if (! is_file(Cms::path('docs/'.$document))) {
        throw new RuntimeException('A public core contract is missing from the distributed package: '.$document);
    }
}

if (! is_file(base_path('server.php')) || hash_file('sha256', base_path('server.php')) !== hash_file('sha256', Cms::path('stubs/application/server.php'))) {
    throw new RuntimeException('The distributed setup did not generate its canonical Laravel development router.');
}
