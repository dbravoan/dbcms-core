<?php

declare(strict_types=1);

use DbCms\Core\Cms;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;

it('keeps editorial consumers independent of optional module namespaces', function (string $file): void {
    $source = (string) file_get_contents(Cms::path($file));
    expect($source)->not->toContain('DbCms\\Modules\\ContentAudit')
        ->and($source)->not->toContain('->revisions()');
    if (str_contains($file, '/Capabilities/')) {
        expect($source)->not->toContain('content_audit');
    }
    $nodes = (new ParserFactory)->createForNewestSupportedVersion()->parse($source);
    $finder = new NodeFinder;
    foreach ($finder->findInstanceOf($nodes, Name::class) as $name) {
        expect($name->toString())->not->toStartWith('DbCms\\Modules\\ContentAudit');
    }
    foreach ($finder->findInstanceOf($nodes, MethodCall::class) as $call) {
        if ($call->name instanceof Identifier) {
            expect($call->name->toString())->not->toBe('revisions');
        }
    }
})->with(['src/Services/Capabilities/EntityMutationService.php', 'src/Services/Capabilities/ContentSectionActions.php', 'src/Services/Admin/AdminNavigationService.php', 'src/Services/Admin/AdminDashboardService.php']);
