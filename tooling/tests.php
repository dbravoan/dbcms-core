<?php

declare(strict_types=1);

define('DBCMS_TOOLING_LIBRARY', true);
require __DIR__.'/package.php';

function verify(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

$config = ['core' => '0.0.1', 'theme' => 'default-clean', 'require' => []];
$manifest = readJson(dirname(__DIR__).'/composer.json');
putenv('DBCMS_TRANSITION_REPOSITORY');
$fixture = fixtureManifest(['require' => ['laravel/framework' => '^13.0']], $manifest, $config, '0.0.1');
verify(count($fixture['repositories']) === 1, 'Normal fixtures must have exactly one local repository.');
verify($fixture['repositories'][0]['type'] === 'path', 'The package under test must be the only path repository.');
verify($fixture['require'][$manifest['name']] === '0.0.1', 'The fixture must require its exact candidate.');
verify($fixture['require']['dbravoan/dbcms-core'] === '0.0.1', 'Core must remain publicly pinned.');
verify(isset($fixture['require']['dbravoan/dbcms-theme-default-clean']), 'A public theme must be explicitly installed.');
foreach (['dbcms-starter', 'dbcms-blocks-basic', 'dbcms-blocks-charts', 'dbcms-admin-theme-dbcms'] as $retired) {
    verify(! isset($fixture['require']['dbravoan/'.$retired]), 'Absorbed or retired package leaked into fixture.');
}
foreach (['module' => 'dbcms-module', 'theme' => 'dbcms-theme', 'admin-theme' => 'dbcms-admin-theme', 'block-package' => 'dbcms-block-package'] as $kind => $type) {
    verify(packageKind(['name' => 'vendor/example', 'type' => $type], []) === $kind, 'Wrong distribution probe for '.$type);
}
verify(packageKind(['name' => 'dbravoan/dbcms-core'], []) === 'core', 'Core is a standalone distribution.');
foreach (['src/Example.php', 'Resources/views/index.blade.php', 'composer.json'] as $entry) {
    assertDistributionEntry($entry);
}
foreach (['tests/Fixture.php', '.build/app/composer.json', '.github/workflows/quality.yml', '.env', 'Resources/.env.production', '../secret', '/absolute'] as $entry) {
    $rejected = false;
    try {
        assertDistributionEntry($entry);
    } catch (RuntimeException) {
        $rejected = true;
    }
    verify($rejected, 'Unsafe archive entry accepted: '.$entry);
}
putenv('DBCMS_TRANSITION_REPOSITORY='.__DIR__);
verify(transitionRepositories() === [['type' => 'artifact', 'url' => __DIR__]], 'Transition archives must be explicitly selected.');
putenv('DBCMS_TRANSITION_REPOSITORY=relative');
$rejected = false;
try {
    transitionRepositories();
} catch (RuntimeException) {
    $rejected = true;
}
verify($rejected, 'A relative transition directory could resolve outside its intended checkout.');
putenv('DBCMS_TRANSITION_REPOSITORY');
$temporary = sys_get_temp_dir().'/dbcms-tooling-test-'.bin2hex(random_bytes(8));
try {
    $testPackages = ['fixturePackages' => ['tests/Fixtures/blocks', 'tests/Fixtures/theme']];
    foreach (['blocks' => 'dbcms-block-package', 'theme' => 'dbcms-theme'] as $name => $type) {
        ensureDirectory($temporary.'/tests/Fixtures/'.$name);
        writeJson($temporary.'/tests/Fixtures/'.$name.'/composer.json', [
            'name' => 'fixture/'.$name, 'version' => '0.0.1', 'type' => $type,
        ]);
        file_put_contents($temporary.'/tests/Fixtures/'.$name.'/README.md', 'Synthetic fixture');
    }
    $fixtures = fixtureRepositories($temporary, $testPackages);
    $again = fixtureRepositories($temporary, $testPackages);
    verify($fixtures === $again, 'Test extension archives must be deterministic for committed locks.');
    verify(count($fixtures) === 2, 'Both generic extension fixtures must be packaged.');
    foreach ($fixtures as $repository) {
        verify($repository['type'] === 'package' && $repository['package']['dist']['type'] === 'zip', 'Test extensions must not introduce another path dependency.');
    }
    symlink($temporary.'/tests', $temporary.'/generated-link');
    removeTree($temporary.'/generated-link');
    verify(! is_link($temporary.'/generated-link') && is_file($temporary.'/tests/Fixtures/theme/README.md'), 'Generated directory cleanup must never follow a root symlink.');

} finally {
    removeTree($temporary);
}
fwrite(STDOUT, "Tooling boundary checks passed.\n");
