<?php

declare(strict_types=1);

/** Standalone package tooling; the application and dependencies are public. */
if (defined('DBCMS_TOOLING_LIBRARY')) {
    return;
}
$root = dirname(__DIR__);
$config = require $root.'/tests/package.php';
$command = $argv[1] ?? 'test';
$profile = getenv('DBCMS_TEST_PROFILE') ?: 'default';
if ($command === 'check' && $profile === 'default' && isset($config['checkProfile'])) {
    $profile = $config['checkProfile'];
    putenv('DBCMS_TEST_PROFILE='.$profile);
}
if (preg_match('/^[a-z][a-z0-9_-]*$/D', $profile) !== 1) {
    throw new RuntimeException('Invalid test profile.');
}
if ($profile !== 'default') {
    if (! isset($config['profiles'][$profile])) {
        throw new RuntimeException('Unknown test profile: '.$profile);
    }
    $config = array_replace($config, $config['profiles'][$profile]);
}
$fixture = $root.'/tests/fixture'.($profile === 'default' ? '' : '/'.$profile);
$fixtureVersion = $config['fixtureVersion'] ?? '0.0.1';
if (transitionRepositories() !== []) {
    $fixture = $root.'/.build/transition-fixture'.($profile === 'default' ? '' : '/'.$profile);
}
$manifest = readJson($root.'/composer.json');
$app = $root.'/.build/app';

try {
    if ($command === 'profiles') {
        fwrite(STDOUT, implode("\n", ['default', ...array_keys($config['profiles'] ?? [])])."\n");
        exit(0);
    }
    if (! in_array($command, ['fixture:lock', 'prepare', 'test', 'check', 'test:dist'], true)) {
        throw new RuntimeException('Unknown package command: '.$command);
    }
    $config['fixtureRepositories'] = fixtureRepositories($root, $config);
    foreach (['DB_HOST', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD'] as $key) {
        if (getenv($key) === false) {
            throw new RuntimeException("{$key} is required. Use the disposable Docker composition.");
        }
    }
    putenv('APP_ENV=testing');
    putenv('APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=');
    putenv('CACHE_STORE=array');
    putenv('SESSION_DRIVER=array');
    putenv('QUEUE_CONNECTION=sync');
    putenv('CMS_MODULES='.implode(',', $config['modules']));
    putenv('COMPOSER_ALLOW_SUPERUSER=1');
    putenv('COMPOSER_PROCESS_TIMEOUT=0');
    putenv('COMPOSER_CACHE_DIR='.$root.'/.build/composer-cache');

    if ($command === 'fixture:lock') {
        // Explicit regeneration starts from the pinned official skeleton. The
        // generated app is disposable; package sources and shared caches remain.
        removeTree($app);
        laravel($app, $config);
        $composer = fixtureManifest(readJson($app.'/.laravel-composer.json'), $manifest, $config, $fixtureVersion);
        writeJson($app.'/composer.json', $composer);
        // An explicit regeneration must resolve the current path metadata, even
        // when its development version and reference have not changed.
        if (is_file($app.'/composer.lock')) {
            unlink($app.'/composer.lock');
        }
        removeTree($app.'/vendor');
        run(['composer', 'update', '--prefer-dist', '--no-interaction', '--no-scripts'], $app);
        setup($app, $config);
        run(['npm', 'install', '--package-lock-only', '--ignore-scripts', '--no-audit', '--no-fund'], $app);
        ensureDirectory($fixture);
        copy($app.'/composer.json', $fixture.'/composer.json');
        copy($app.'/composer.lock', $fixture.'/composer.lock');
        copy($app.'/package-lock.json', $fixture.'/package-lock.json');
        fwrite(STDOUT, transitionRepositories() === []
            ? "Fixture locked. Commit tests/fixture/composer.json and composer.lock together.\n"
            : "Transition fixture locked in .build only. Regenerate the public fixture after publication.\n");
        exit(0);
    }

    prepare($app, $root, $config, $manifest, $fixture, $fixtureVersion);
    if ($command === 'prepare') {
        exit(0);
    }
    if ($command === 'test') {
        $paths = array_map(static fn (string $path): string => $root.'/tests/'.$path, $config['testPaths']);
        run([PHP_BINARY, '-d', 'memory_limit=1G', $app.'/vendor/bin/pest', '--test-directory=../../tests', '--configuration='.$root.'/phpunit.xml', ...$paths], $root);
        frontendTests($root);
    }
    if ($command === 'check') {
        run(['composer', 'validate', '--strict', '--no-check-lock'], $root);
        run([PHP_BINARY, $app.'/vendor/bin/pint', '--test', '--config='.$root.'/pint.json', $root], $root);
        $preload = '';
        foreach ($config['bootstrapFiles'] ?? [] as $file) {
            $preload .= 'require_once '.var_export($root.'/'.$file, true).'; ';
        }
        $bootstrap = '<?php require '.var_export($app.'/vendor/autoload.php', true).'; '.$preload.'$app = require '.var_export($app.'/bootstrap/app.php', true).'; $app->make(\\Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();';
        file_put_contents($root.'/.build/phpstan-bootstrap.php', $bootstrap);
        $neon = "includes:\n    - {$app}/vendor/larastan/larastan/extension.neon\n\nparameters:\n    level: 8\n    tmpDir: {$root}/.build/phpstan\n    bootstrapFiles:\n        - {$root}/.build/phpstan-bootstrap.php\n    paths:\n";
        foreach ($config['sourceDirs'] as $path) {
            $neon .= '        - '.json_encode($root.'/'.$path, JSON_THROW_ON_ERROR)."\n";
        }
        $neon .= "    excludePaths:\n        analyseAndScan:\n";
        foreach (['.build', 'tests', 'tooling', 'vendor', 'node_modules'] as $path) {
            $neon .= '            - '.json_encode($root.'/'.$path.'/*', JSON_THROW_ON_ERROR)."\n";
        }
        $migrationPaths = [$app.'/vendor/dbravoan/dbcms-core/database/migrations', $app.'/vendor/dbravoan/dbcms-core/src/Content/Pages/Database/Migrations'];
        $configPaths = [$app.'/config', $app.'/vendor/dbravoan/dbcms-core/config'];
        $locked = readJson($fixture.'/composer.lock');
        foreach ($locked['packages'] as $dependency) {
            if (($dependency['type'] ?? null) !== 'dbcms-module') {
                continue;
            }
            foreach ($dependency['autoload']['psr-4'] ?? [] as $directory) {
                foreach ((array) $directory as $path) {
                    $base = realpath($app.'/vendor/'.$dependency['name'].'/'.rtrim($path, '/'));
                    if ($base === false) {
                        continue;
                    }
                    if (is_dir($base.'/Database/Migrations')) {
                        $migrationPaths[] = $base.'/Database/Migrations';
                    }
                    foreach (['Config', 'config'] as $configurationDirectory) {
                        if (is_dir($base.'/'.$configurationDirectory)) {
                            $configPaths[] = $base.'/'.$configurationDirectory;
                        }
                    }
                }
            }
        }
        if (($config['phpstanScanFiles'] ?? []) !== []) {
            $neon .= "    scanFiles:\n";
            foreach ($config['phpstanScanFiles'] as $file) {
                $neon .= '        - '.json_encode($root.'/'.$file, JSON_THROW_ON_ERROR)."\n";
            }
        }
        $neon .= "    databaseMigrationsPath:\n";
        foreach ($migrationPaths as $path) {
            $neon .= '        - '.json_encode($path, JSON_THROW_ON_ERROR)."\n";
        }
        $neon .= "    configDirectories:\n";
        foreach ($configPaths as $path) {
            $neon .= '        - '.json_encode($path, JSON_THROW_ON_ERROR)."\n";
        }
        $neon .= "    treatPhpDocTypesAsCertain: false\n";
        file_put_contents($root.'/.build/phpstan.neon', $neon);
        if ($config['sourceDirs'] !== []) {
            run([PHP_BINARY, $app.'/vendor/bin/phpstan', 'analyse', '--configuration='.$root.'/.build/phpstan.neon', '--memory-limit=2G'], $root);
        } else {
            fwrite(STDOUT, "No owned PHP runtime sources: theme behavior is checked through Laravel tests and its compiled assets.\n");
        }
        run(['composer', 'audit', '--locked'], $app);
        run(['npm', 'ci', '--no-audit', '--no-fund'], $app);
        run(['npm', 'run', 'build'], $app);
    }
    if ($command === 'test:dist') {
        $archiveDir = $root.'/.build/dist';
        ensureDirectory($archiveDir);
        $archive = $archiveDir.'/candidate.zip';
        if (is_file($archive)) {
            unlink($archive);
        }
        run(['composer', 'archive', '--format=zip', '--dir='.$archiveDir, '--file=candidate'], $root);
        $zip = new ZipArchive;
        if ($zip->open($archive) !== true) {
            throw new RuntimeException('Candidate archive cannot be read.');
        }
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->getNameIndex($i);
            if (is_string($entry)) {
                assertDistributionEntry($entry);
            }
        }
        $gitArchive = $archiveDir.'/candidate-git.zip';
        run(['git', 'archive', '--worktree-attributes', '--format=zip', '--output='.$gitArchive, 'HEAD'], $root);
        $gitZip = new ZipArchive;
        if ($gitZip->open($gitArchive) !== true) {
            throw new RuntimeException('Git export archive cannot be read.');
        }
        for ($i = 0; $i < $gitZip->numFiles; $i++) {
            $entry = $gitZip->getNameIndex($i);
            if (is_string($entry) && is_file($root.'/'.$entry) && $zip->locateName($entry) === false) {
                throw new RuntimeException('Git export includes a file excluded by Composer: '.$entry);
            }
        }
        $gitZip->close();
        $zip->close();

        $distApp = $root.'/.build/dist-app';
        removeTree($distApp);
        laravel($distApp, $config);
        $composer = readJson($fixture.'/composer.json');
        $package = $manifest;
        $package['version'] = $fixtureVersion;
        $package['dist'] = ['type' => 'zip', 'url' => 'file://'.$archive, 'reference' => hash_file('sha256', $archive), 'shasum' => hash_file('sha1', $archive)];
        unset($package['scripts'], $package['archive']);
        $composer['repositories'] = [['type' => 'package', 'package' => $package], ...array_values(array_filter($composer['repositories'] ?? [], static fn (array $repository): bool => ($repository['type'] ?? null) !== 'path'))];
        unset($composer['autoload-dev']);
        $composer['config']['optimize-autoloader'] = true;
        writeJson($distApp.'/composer.json', $composer);
        $lock = readJson($fixture.'/composer.lock');
        foreach ($lock['packages'] as &$lockedPackage) {
            if ($lockedPackage['name'] === $manifest['name']) {
                $lockedPackage = $package;
            }
        }
        unset($lockedPackage);
        writeJson($distApp.'/composer.lock', $lock);
        // Refresh repository metadata without changing any locked public version.
        run(['composer', 'update', '--lock', '--no-install', '--no-interaction', '--no-scripts'], $distApp);
        run(['composer', 'install', '--prefer-dist', '--no-interaction', '--no-scripts'], $distApp);
        setup($distApp, $config);
        $probe = $root.'/.build/dist-probe.json';
        writeJson($probe, [
            'kind' => packageKind($manifest, $config),
            'modules' => $config['distModules'] ?? $config['modules'],
            'preserveRows' => $config['preserveRows'] ?? [],
            'blockTypes' => $config['blockTypes'] ?? [],
            'probe' => $config['distProbe'] ?? null,
        ]);
        run([PHP_BINARY, $root.'/tooling/dist-smoke.php', $distApp, $manifest['name'], ($manifest['extra']['dbcms']['identifier'] ?? 'core'), $probe], $distApp);
    }
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage()."\n");
    exit(1);
}

/** @return array<string, mixed> */
function readJson(string $file): array
{
    $contents = file_get_contents($file);
    if ($contents === false) {
        throw new RuntimeException('Cannot read '.$file);
    }
    $json = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
    if (! is_array($json)) {
        throw new RuntimeException('Expected JSON object: '.$file);
    }

    return $json;
}

/** @param array<string, mixed> $value */
function writeJson(string $file, array $value): void
{
    file_put_contents($file, json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n");
}

/** @param list<string> $arguments */
function run(array $arguments, string $directory): void
{
    fwrite(STDOUT, '+ '.implode(' ', $arguments)."\n");
    $process = proc_open($arguments, [STDIN, STDOUT, STDERR], $pipes, $directory);
    if (! is_resource($process) || proc_close($process) !== 0) {
        throw new RuntimeException('Command failed: '.implode(' ', $arguments));
    }
}

function ensureDirectory(string $directory): void
{
    if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
        throw new RuntimeException('Cannot create '.$directory);
    }
}

function removeTree(string $directory): void
{
    if (is_link($directory)) {
        unlink($directory);

        return;
    }
    if (! is_dir($directory)) {
        return;
    }
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {
        $file->isDir() && ! $file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    rmdir($directory);
}

/** @param array<string, mixed> $config */
function laravel(string $directory, array $config): void
{
    $version = $config['laravel']['version'];
    $reference = $config['laravel']['reference'];
    if (is_file($directory.'/.laravel-reference')) {
        if (trim((string) file_get_contents($directory.'/.laravel-reference')) !== $reference) {
            throw new RuntimeException('Laravel reference changed; remove only this generated application and retry.');
        }

        return;
    }
    if (is_dir($directory)) {
        throw new RuntimeException('Partial fixture found; remove only '.$directory.' and retry.');
    }
    ensureDirectory(dirname($directory));
    run(['composer', 'create-project', '--no-install', '--no-scripts', '--prefer-dist', '--no-interaction', 'laravel/laravel', $directory, $version], dirname($directory));
    $metadata = readJson('https://repo.packagist.org/p2/laravel/laravel.json');
    $references = [];
    foreach ($metadata['packages']['laravel/laravel'] as $published) {
        if (ltrim($published['version'], 'v') === $version) {
            $references[] = $published['dist']['reference'];
        }
    }
    if ($references !== [$reference]) {
        throw new RuntimeException('Published Laravel version/reference differs from the pinned fixture.');
    }
    writeJson($directory.'/.laravel-composer.json', readJson($directory.'/composer.json'));
    file_put_contents($directory.'/.laravel-reference', $reference);
}

/** @param array<string, mixed> $base
 * @param  array<string, mixed>  $manifest
 * @param  array<string, mixed>  $config
 * @return array<string, mixed>
 */
function fixtureManifest(array $base, array $manifest, array $config, string $version): array
{
    $base['name'] = 'fixture/dbcms-package';
    $base['require']['dbravoan/dbcms-core'] = $config['core'];
    $theme = $config['theme'] ?? 'default-clean';
    if ($theme === 'default-clean' && $manifest['name'] !== 'dbravoan/dbcms-theme-default-clean') {
        $base['require']['dbravoan/dbcms-theme-default-clean'] = '0.0.1';
    }
    $base['require'] = array_replace($base['require'], $config['require'] ?? [], [$manifest['name'] => $version]);
    foreach ($config['fixtureRepositories'] ?? [] as $repository) {
        $base['require'][$repository['package']['name']] = $repository['package']['version'];
    }
    // A root PSR-4 package points back into its generated fixture through vendor.
    $base['config']['optimize-autoloader'] = false;
    $base['config']['audit']['block-insecure'] = true;
    $base['config']['allow-plugins'] = ['pestphp/pest-plugin' => true, 'php-http/discovery' => true];
    $base['require-dev'] = array_replace($base['require-dev'] ?? [], [
        'fakerphp/faker' => '^1.23', 'larastan/larastan' => '^3.0',
        'laravel/pint' => '^1.14', 'laravel/sail' => '^1.26',
        'mockery/mockery' => '^1.6', 'nunomaduro/collision' => '^8.1',
        'pestphp/pest' => '^4.0', 'pestphp/pest-plugin-arch' => '^4.0',
        'pestphp/pest-plugin-laravel' => '^4.0', 'phpstan/phpstan' => '^2.2',
    ]);
    unset($base['require-dev']['phpunit/phpunit']);
    $base['repositories'] = [[
        'type' => 'path', 'url' => '../..',
        'options' => ['symlink' => true, 'reference' => 'none', 'versions' => [$manifest['name'] => $version]],
    ], ...($config['fixtureRepositories'] ?? []), ...transitionRepositories()];
    unset($base['scripts'], $base['archive']);
    $base['autoload-dev'] = ['psr-4' => ['Tests\\' => '../../tests/'], 'exclude-from-classmap' => ['../../tests/Fixtures/']];

    return $base;
}

/** @return list<array<string, string>> */
function transitionRepositories(): array
{
    $directory = getenv('DBCMS_TRANSITION_REPOSITORY');
    if ($directory === false || $directory === '') {
        return [];
    }
    if (! str_starts_with($directory, '/') || ! is_dir($directory)) {
        throw new RuntimeException('DBCMS_TRANSITION_REPOSITORY must be an existing absolute archive directory.');
    }

    return [['type' => 'artifact', 'url' => $directory]];
}

/** @param array<string, mixed> $manifest
 * @param  array<string, mixed>  $config
 */
function packageKind(array $manifest, array $config): string
{
    $kind = $config['kind'] ?? match ($manifest['type'] ?? 'library') {
        'dbcms-module' => 'module',
        'dbcms-theme' => 'theme',
        'dbcms-admin-theme' => 'admin-theme',
        'dbcms-block-package' => 'block-package',
        default => $manifest['name'] === 'dbravoan/dbcms-core' ? 'core' : '',
    };
    if (! in_array($kind, ['core', 'module', 'theme', 'admin-theme', 'block-package'], true)) {
        throw new RuntimeException('Declare a supported package kind in tests/package.php.');
    }

    return $kind;
}

/** @param array<string, mixed> $config */
function setup(string $app, array $config): void
{
    environment($app);
    run([PHP_BINARY, $app.'/artisan', 'package:discover', '--quiet'], $app);
    run([PHP_BINARY, $app.'/artisan', 'cms:setup',
        '--theme='.($config['theme'] ?? 'default-clean'),
        '--admin-theme='.($config['adminTheme'] ?? 'dbcms'),
        '--modules='.implode(',', $config['modules']),
        '--blocks='.implode(',', $config['blockPackages'] ?? []),
        '--no-interaction',
    ], $app);
    run(['composer', 'dump-autoload', '--no-scripts', '--no-interaction'], $app);
}

/** @param array<string, mixed> $config
 * @param  array<string, mixed>  $manifest
 */
function prepare(string $app, string $root, array $config, array $manifest, string $fixture, string $fixtureVersion): void
{
    if (! is_file($fixture.'/composer.lock')) {
        throw new RuntimeException('Missing fixture lock. Run composer fixture:lock and commit it first.');
    }
    $fixtureManifest = readJson($fixture.'/composer.json');
    if (($fixtureManifest['require'][$manifest['name']] ?? null) !== $fixtureVersion) {
        throw new RuntimeException('Fixture package name differs. Run composer fixture:lock after renaming.');
    }
    $lock = readJson($fixture.'/composer.lock');
    foreach ($lock['packages'] as $locked) {
        if ($locked['name'] === $manifest['name']) {
            foreach (['require', 'autoload', 'type', 'extra'] as $key) {
                if (canonical($locked[$key] ?? []) !== canonical($manifest[$key] ?? [])) {
                    throw new RuntimeException('Package '.$key.' changed. Run composer fixture:lock.');
                }
            }
        }
    }
    laravel($app, $config);
    copy($fixture.'/composer.json', $app.'/composer.json');
    copy($fixture.'/composer.lock', $app.'/composer.lock');
    run(['composer', 'install', '--prefer-dist', '--no-interaction', '--no-scripts'], $app);
    $installed = readJson($app.'/vendor/composer/installed.json');
    foreach ($installed['packages'] as $package) {
        if ($package['name'] !== $manifest['name']) {
            continue;
        }
        foreach (['require', 'autoload', 'type', 'extra'] as $key) {
            if (canonical($package[$key] ?? []) !== canonical($manifest[$key] ?? [])) {
                // Composer can retain installed metadata for an unchanged path version.
                // Recreate only this generated dependency tree from its reviewed lock.
                fwrite(STDOUT, "Candidate metadata changed; rebuilding the fixture vendor from its lock.\n");
                removeTree($app.'/vendor');
                run(['composer', 'install', '--prefer-dist', '--no-interaction', '--no-scripts'], $app);
                break 2;
            }
        }
    }
    setup($app, $config);
    if (! is_file($fixture.'/package-lock.json')) {
        throw new RuntimeException('Missing frontend fixture lock. Run composer fixture:lock.');
    }
    copy($fixture.'/package-lock.json', $app.'/package-lock.json');
}

function environment(string $app): void
{
    foreach (['bootstrap/cache', 'storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs'] as $path) {
        ensureDirectory($app.'/'.$path);
    }
    file_put_contents($app.'/.env', "APP_ENV=testing\nAPP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=\nDB_CONNECTION=mysql\n");
}

function frontendTests(string $root): void
{
    if (! is_file($root.'/package.json')) {
        return;
    }
    $package = readJson($root.'/package.json');
    run(['npm', 'ci', '--no-audit', '--no-fund'], $root);
    if (isset($package['scripts']['test'])) {
        run(['npm', 'test'], $root);
    }
    if (isset($package['scripts']['build'])) {
        run(['npm', 'run', 'build'], $root);
    }
}

/** Normalize JSON metadata independently of object key order. */
function canonical(mixed $value): mixed
{
    if (! is_array($value)) {
        return $value;
    }
    foreach ($value as &$item) {
        if (is_array($item)) {
            $item = canonical($item);
        }
    }
    unset($item);
    ksort($value);

    return $value;
}

function assertDistributionEntry(string $entry): void
{
    if (preg_match('~^(?:\.build|\.github|tests|tooling|docker|vendor|node_modules)/~', $entry)
        || preg_match('~(?:^|/)\.env(?:\.[^/]*)?$~D', $entry)
        || str_starts_with($entry, '/') || in_array('..', explode('/', $entry), true)) {
        throw new RuntimeException('Development, secret or unsafe content leaked into distribution: '.$entry);
    }
}

/** Build repository-owned test extensions as deterministic ZIP packages, never path dependencies.
 * @param  array<string, mixed>  $config
 * @return list<array<string, mixed>>
 */
function fixtureRepositories(string $root, array $config): array
{
    $repositories = [];
    foreach ($config['fixturePackages'] ?? [] as $directory) {
        $base = realpath($root.'/'.$directory);
        if ($base === false || ! str_starts_with($base, $root.'/tests/Fixtures/')) {
            throw new RuntimeException('Test extension must belong to tests/Fixtures: '.$directory);
        }
        $package = readJson($base.'/composer.json');
        $package['version'] ??= '0.0.1';
        $slug = str_replace('/', '-', $package['name']);
        $archive = $root.'/.build/fixture-packages/'.$slug.'.zip';
        ensureDirectory(dirname($archive));
        $zip = new ZipArchive;
        if ($zip->open($archive, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Cannot create test extension archive.');
        }
        $entries = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->isLink()) {
                throw new RuntimeException('A test extension cannot include symlinks.');
            }
            if ($file->isFile()) {
                $entries[] = substr($file->getPathname(), strlen($base) + 1);
            }
        }
        sort($entries);
        foreach ($entries as $entry) {
            assertDistributionEntry($entry);
            $content = $entry === 'composer.json'
                ? json_encode($package, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n"
                : (string) file_get_contents($base.'/'.$entry);
            $zip->addFromString($entry, $content);
            $zip->setMtimeName($entry, 315532800);
            $zip->setExternalAttributesName($entry, ZipArchive::OPSYS_UNIX, 0100644 << 16);
        }
        $zip->close();
        $package['dist'] = ['type' => 'zip', 'url' => 'file://'.$archive, 'reference' => hash_file('sha256', $archive), 'shasum' => hash_file('sha1', $archive)];
        unset($package['scripts'], $package['archive']);
        $repositories[] = ['type' => 'package', 'package' => $package];
    }

    return $repositories;
}
