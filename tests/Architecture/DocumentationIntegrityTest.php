<?php

declare(strict_types=1);

use DbCms\Core\Cms;
use DbCms\Core\Extensions\ComposerPackages;
use DbCms\Core\Modules\ModuleManifestGraph;
use Illuminate\Support\Facades\Artisan;

/** @return list<string> */
function documentedFiles(): array
{
    $files = array_map(Cms::path(...), ['README.md', 'AGENTS.md', 'CONTRIBUTING.md', 'SECURITY.md', 'CHANGELOG.md']);

    if (is_file(Cms::path('.gemini/GEMINI.md'))) {
        $files[] = Cms::path('.gemini/GEMINI.md');
    }

    foreach (['docs'] as $directory) {
        // Agent instructions are intentionally excluded from runtime artifacts.
        if (! is_dir(Cms::path($directory))) {
            continue;
        }
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(Cms::path($directory), FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->isFile() && in_array($file->getExtension(), ['md', 'json'], true) && ! str_contains($file->getPathname(), '/archive/')) {
                $files[] = $file->getPathname();
            }
        }
    }

    sort($files);

    return $files;
}

function documentationIsCurrent(string $content): bool
{
    return preg_match('~^\h*(?:>\h*)?(?:\*\*)?Status(?:\*\*)?:\h*(?:`|\*\*)?superseded\b~mi', $content) !== 1;
}

/** @return list<string> */
function documentedArtisanCommands(string $content): array
{
    preg_match_all(
        '~(?<![\w/.\-])(?:php\h+artisan|(?:(?:\./)?vendor/bin/sail|sail)\h+(?:php\h+)?artisan)\h+([a-z][a-z0-9:._-]*)~i',
        $content,
        $matches,
    );

    // Explicit inline command declarations also cover prose without a shell
    // prefix. Arbitrary a:b values can be permissions, cache keys or examples.
    preg_match_all(
        '~(?:\b(?:command|comando)\h+`([a-z][a-z0-9_-]*(?::[a-z][a-z0-9_-]*)+)`|`([a-z][a-z0-9_-]*(?::[a-z][a-z0-9_-]*)+)`\h+(?:command|comando)\b)~',
        $content,
        $inline,
        PREG_SET_ORDER,
    );
    foreach ($inline as $declaration) {
        $matches[1][] = $declaration[1] !== '' ? $declaration[1] : $declaration[2];
    }

    return array_values(array_unique($matches[1]));
}

/** @return list<string> */
function documentedBinScripts(string $content): array
{
    preg_match_all('~(?<![\w/])(?:\./)?bin/([a-z0-9_-]+(?:\.(?:sh|mjs|py))?)(?=[`\s),;]|$)~i', $content, $matches);

    return array_values(array_unique($matches[1]));
}

test('artisan documentation extraction recognizes command entry points and ignores unrelated text', function (string $content, array $expected): void {
    expect(documentedArtisanCommands($content))->toBe($expected);
})->with([
    'official Sail' => ['./vendor/bin/sail artisan migrate --force', ['migrate']],
    'Sail without dot prefix' => ['vendor/bin/sail artisan cms:project:init example', ['cms:project:init']],
    'Sail php passthrough' => ['./vendor/bin/sail php artisan config:cache', ['config:cache']],
    'Sail alias' => ['sail artisan list', ['list']],
    'container or production PHP' => ['php artisan queue:restart', ['queue:restart']],
    'unknown official command remains detectable' => ['./vendor/bin/sail artisan cms:missing-doc-command', ['cms:missing-doc-command']],
    'retired inline command remains detectable' => ['The `sections:normalize` command is safe.', ['sections:normalize']],
    'Spanish inline command declaration' => ['Ejecutar el comando `cms:install`.', ['cms:install']],
    'scopes and cache keys are not command declarations' => ['`manage:modules`, `confirm:true`, `cms:page-cache:version`', []],
    'flags and other Sail commands' => ['./vendor/bin/sail test; ./vendor/bin/sail artisan --help', []],
    'prose and filenames' => ['Artisan::call("migrate"); docs/vendor/bin/sail; artisan command reference', []],
    'repeated command' => ["./vendor/bin/sail artisan migrate\nphp artisan migrate", ['migrate']],
]);

test('documentation status distinguishes current contracts from historical specifications', function (): void {
    expect(documentationIsCurrent("# Old plan\nStatus: superseded\nphp artisan sections:normalize"))->toBeFalse()
        ->and(documentationIsCurrent("# Current plan\nStatus: approved\nRetired aliases are superseded."))->toBeTrue();
});

test('script extraction includes extensionless project entry points without matching vendor paths', function (): void {
    expect(documentedBinScripts('`bin/ci-check` ./bin/test-frontend.mjs vendor/bin/sail `bin/missing-command`'))->toBe(['ci-check', 'test-frontend.mjs', 'missing-command']);
});

test('every artisan command referenced in the docs exists', function (): void {
    $modules = app(ComposerPackages::class)->modules();
    $graph = new ModuleManifestGraph($modules, (string) config('cms.version'));
    foreach ($graph->ordered(array_keys($modules)) as $identifier) {
        app()->register($modules[$identifier]['provider']);
    }
    $available = array_keys(Artisan::all());
    $missing = [];

    foreach (documentedFiles() as $file) {
        $content = (string) file_get_contents($file);
        if (! documentationIsCurrent($content)) {
            continue;
        }
        foreach (documentedArtisanCommands($content) as $command) {
            if (! in_array($command, $available, true)) {
                $missing[] = "{$command} (".basename($file).')';
            }
        }
    }

    expect($missing)->toBeEmpty('Unknown artisan commands: '.implode(', ', $missing));
});

test('every bin script referenced in the docs exists', function (): void {
    $missing = [];

    foreach (documentedFiles() as $file) {
        $content = (string) file_get_contents($file);
        if (! documentationIsCurrent($content)) {
            continue;
        }
        foreach (documentedBinScripts($content) as $script) {
            if (! is_file(Cms::path('bin/'.$script))) {
                $missing[] = "bin/{$script} (".basename($file).')';
            }
        }
    }

    expect($missing)->toBeEmpty('Unknown bin scripts: '.implode(', ', $missing));
});
