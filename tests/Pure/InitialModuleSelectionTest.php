<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

it('selects no optional modules unless the installer composition explicitly names them', function (): void {
    $root = PACKAGE_ROOT.'/.build/app';
    $script = <<<'PHP'
require $argv[1].'/vendor/autoload.php';
$config = require DbCms\Core\Cms::path('config/cms.php');
echo json_encode($config['modules'], JSON_THROW_ON_ERROR);
PHP;
    /** @return list<string> */
    $run = static function (string|false $selection) use ($root, $script): array {
        $process = new Process([PHP_BINARY, '-r', $script, $root], $root, ['CMS_MODULES' => $selection]);
        $process->mustRun();

        return json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
    };

    expect($run(false))->toBe([])
        ->and($run('content_audit,blog'))->toBe(['content_audit', 'blog']);
});
