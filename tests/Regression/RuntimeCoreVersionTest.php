<?php

declare(strict_types=1);

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

test('runtime core version identifies the installed Composer package independently of project version labels', function (): void {
    $root = PACKAGE_ROOT.'/.build/app';
    $fixture = sys_get_temp_dir().'/dbcms-version-config-'.bin2hex(random_bytes(8));
    mkdir($fixture, 0700);
    file_put_contents($fixture.'/VERSION', "9.8.7\n");
    $script = <<<'PHP'
    require $argv[1].'/vendor/autoload.php';
    new Illuminate\Foundation\Application($argv[2]);
    $configuration = require DbCms\Core\Cms::path('config/cms.php');
    echo json_encode(['configured' => $configuration['version'], 'installed' => Composer\InstalledVersions::getPrettyVersion('dbravoan/dbcms-core')], JSON_THROW_ON_ERROR);
    PHP;
    try {
        $process = new Process([PHP_BINARY, '-r', $script, $root, $fixture], $root, ['APP_VERSION' => 'project-label']);
        $process->mustRun();
        $versions = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        expect($versions['installed'])->toBeString()->not->toBeEmpty()
            ->and($versions['configured'])->toBe($versions['installed'])->not->toBe('project-label')->not->toBe('9.8.7');
    } finally {
        (new Filesystem)->remove($fixture);
    }
});
