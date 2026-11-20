<?php

declare(strict_types=1);

use DbCms\Core\Blocks\BlockPackageManager;
use DbCms\Core\Content\Pages\Models\Page;
use DbCms\Core\Sections\SectionEditorService;
use DbCms\Core\Sections\SectionSyncService;
use DbCms\Core\Sections\UnavailableBlockGuard;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {});

test('disabled blocks reject insertion and mutation while another field remains editable', function (): void {
    app(BlockPackageManager::class)->setEnabled('fixture_blocks', false);
    $guard = app(UnavailableBlockGuard::class);
    $old = ['type' => 'text', 'columns' => [['id' => 'main', 'blocks' => [
        ['id' => 'retained', 'type' => 'fixture_chart', 'title' => 'Saved ROI'],
        ['id' => 'editable', 'type' => 'rich_text', 'content' => 'Before'],
    ]]]];
    $new = $old;
    $new['columns'][0]['blocks'][1]['content'] = 'After';
    expect($guard->reconcile($new, $old)['columns'][0]['blocks'][0])->toBe($old['columns'][0]['blocks'][0]);
    $new['columns'][0]['blocks'][0]['title'] = 'Tampered';
    expect(fn () => $guard->reconcile($new, $old))->toThrow(ValidationException::class)
        ->and(fn () => $guard->reconcile(['type' => 'fixture_chart']))->toThrow(ValidationException::class);
});

test('adding columns cannot bypass a stored unavailable block', function (): void {
    app(BlockPackageManager::class)->setEnabled('fixture_blocks', false);
    $stored = ['id' => 10, 'type' => 'fixture_chart', 'title' => 'Keep'];
    $changed = $stored + ['columns' => [['blocks' => [['type' => 'rich_text', 'content' => 'Replacement']]]]];
    expect(fn () => app(UnavailableBlockGuard::class)->reconcile($changed, $stored))->toThrow(ValidationException::class);
});

test('omitting unavailable nested content restores the stored node', function (): void {
    app(BlockPackageManager::class)->setEnabled('fixture_blocks', false);
    $old = ['type' => 'text', 'columns' => [['id' => 'main', 'blocks' => [['id' => 'saved', 'type' => 'fixture_chart', 'title' => 'Keep']]]]];
    $new = ['type' => 'text', 'columns' => [['id' => 'main', 'blocks' => []]]];
    expect(app(UnavailableBlockGuard::class)->reconcile($new, $old)['columns'][0]['blocks'])->toBe($old['columns'][0]['blocks']);
});

test('unavailable blocks without identities survive deletion and reordering of active siblings', function (bool $keepActiveSibling): void {
    app(BlockPackageManager::class)->setEnabled('fixture_blocks', false);
    $active = ['type' => 'rich_text', 'content' => 'Editable'];
    $unavailable = ['type' => 'fixture_chart', 'title' => 'Keep'];
    $stored = ['type' => 'text', 'columns' => [['id' => 'main', 'blocks' => [$active, $unavailable]]]];
    $children = $keepActiveSibling ? [$unavailable, $active] : [$unavailable];
    $incoming = ['type' => 'text', 'columns' => [['id' => 'main', 'blocks' => $children]]];

    $result = app(UnavailableBlockGuard::class)->reconcile($incoming, $stored)['columns'][0]['blocks'];
    $expected = $children;
    foreach ($result as $index => $child) {
        if (($child['type'] ?? '') === 'rich_text') {
            expect($child['id'])->toBeString()->not->toBeEmpty();
            unset($result[$index]['id']);
        }
    }
    expect($result)->toBe($expected);
})->with(['delete active sibling' => false, 'reorder active sibling' => true]);

test('a stored unavailable block cannot authorize duplicate insertion', function (bool $withIdentity): void {
    app(BlockPackageManager::class)->setEnabled('fixture_blocks', false);
    $unavailable = ['type' => 'fixture_chart', 'title' => 'Keep'];
    if ($withIdentity) {
        $unavailable['id'] = 'saved';
    }
    $stored = ['type' => 'text', 'columns' => [['id' => 'main', 'blocks' => [$unavailable]]]];
    $incoming = ['type' => 'text', 'columns' => [['id' => 'main', 'blocks' => [$unavailable, $unavailable]]]];

    expect(fn () => app(UnavailableBlockGuard::class)->reconcile($incoming, $stored))->toThrow(ValidationException::class);
})->with(['without identity' => false, 'with identity' => true]);

test('omitting a nested layout preserves its unavailable descendants', function (): void {
    app(BlockPackageManager::class)->setEnabled('fixture_blocks', false);
    $nested = ['id' => 'layout', 'type' => 'text', 'columns' => [['id' => 'inner', 'blocks' => [
        ['id' => 'saved', 'type' => 'fixture_chart', 'title' => 'Keep'],
    ]]]];
    $old = ['type' => 'text', 'columns' => [['id' => 'main', 'blocks' => [$nested]]]];
    $new = ['type' => 'text', 'columns' => [['id' => 'main', 'blocks' => []]]];

    expect(app(UnavailableBlockGuard::class)->reconcile($new, $old)['columns'][0]['blocks'])->toBe([$nested]);
});

test('saving a page while its package is disabled preserves its stored sections and translations', function (): void {
    $page = Page::create(['template' => 'default', 'is_published' => false]);
    $sync = app(SectionSyncService::class);
    app(BlockPackageManager::class)->setEnabled('fixture_blocks', true);
    $sync->sync($page, json_encode(['es' => [['type' => 'fixture_chart', 'title' => 'Retained']]], JSON_THROW_ON_ERROR));
    $before = app(SectionEditorService::class)->payload($page);
    app(BlockPackageManager::class)->setEnabled('fixture_blocks', false);
    $sync->sync($page, json_encode(['es' => [['type' => 'rich_text', 'content' => 'New section']]], JSON_THROW_ON_ERROR));
    $after = app(SectionEditorService::class)->payload($page);
    expect(collect($after['es'])->firstWhere('id', $before['es'][0]['id'])['title'])->toBe('Retained');
});

test('section writes require explicit locale keys and preserve stored content on invalid input', function (): void {
    $page = Page::create(['template' => 'default', 'is_published' => false]);
    $sync = app(SectionSyncService::class);
    $sync->sync($page, json_encode(['es' => [['type' => 'rich_text', 'content' => 'Stored']]], JSON_THROW_ON_ERROR));
    $before = app(SectionEditorService::class)->payload($page);

    expect(fn () => $sync->sync($page, json_encode([['type' => 'rich_text', 'content' => 'Ambiguous']], JSON_THROW_ON_ERROR)))
        ->toThrow(ValidationException::class);
    expect(app(SectionEditorService::class)->payload($page->fresh()))->toBe($before);
});

test('malformed section locale payloads cannot silently erase existing content', function (mixed $invalid): void {
    $page = Page::create(['template' => 'default', 'is_published' => false]);
    $sync = app(SectionSyncService::class);
    $sync->sync($page, json_encode(['es' => [['type' => 'rich_text', 'content' => 'Stored']]], JSON_THROW_ON_ERROR));
    $before = app(SectionEditorService::class)->payload($page);

    expect(fn () => $sync->sync($page, json_encode($invalid, JSON_THROW_ON_ERROR)))->toThrow(ValidationException::class);
    expect(app(SectionEditorService::class)->payload($page->fresh()))->toBe($before);
})->with([
    'unknown locale' => [['unknown' => [['type' => 'rich_text', 'content' => 'Replacement']]]],
    'invalid locale list' => [['es' => 'invalid']],
    'invalid section' => [['es' => ['invalid']]],
    'empty block' => [['es' => [[]]]],
    'scalar payload' => ['invalid'],
    'scalar columns' => [['es' => [['type' => 'text', 'columns' => 'invalid']]]],
    'column object instead of list' => [['es' => [['type' => 'text', 'columns' => ['blocks' => []]]]]],
    'invalid column' => [['es' => [['type' => 'text', 'columns' => [123]]]]],
    'null column blocks' => [['es' => [['type' => 'text', 'columns' => [['blocks' => null]]]]]],
    'block object instead of list' => [['es' => [['type' => 'text', 'columns' => [['blocks' => ['type' => 'rich_text']]]]]]],
    'invalid nested block' => [['es' => [['type' => 'text', 'columns' => [['blocks' => [123]]]]]]],
    'invalid descendant block' => [['es' => [['type' => 'text', 'columns' => [['blocks' => [
        ['type' => 'text', 'columns' => [['blocks' => [123]]]],
    ]]]]]]],
]);
