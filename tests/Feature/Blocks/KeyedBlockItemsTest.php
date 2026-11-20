<?php

declare(strict_types=1);

use DbCms\Core\Blocks\BlockItems;
use Illuminate\Validation\ValidationException;

test('a keyed collection can move and update independently of sibling positions', function (): void {
    $schema = ['scenes' => ['type' => 'repeater', 'keyed' => true, 'fields' => [
        'id' => ['type' => 'text'],
        'title' => ['type' => 'text'],
        'children' => ['type' => 'blocks'],
    ]]];
    $node = ['scenes' => [
        ['id' => 'first', 'title' => 'Opening', 'children' => [['id' => 'heading', 'type' => 'heading', 'text' => 'Published']]],
        ['id' => 'second', 'title' => 'End'],
    ]];
    $target = [['collection' => 'scenes', 'id' => 'first']];
    $moved = BlockItems::apply($node, $schema, ['op' => 'move_item', 'target' => $target]);
    $edited = BlockItems::apply($moved, $schema, ['op' => 'update_item', 'target' => $target, 'values' => ['title' => 'Updated']]);

    expect(array_column($edited['scenes'], 'id'))->toBe(['second', 'first'])
        ->and($edited['scenes'][1]['title'])->toBe('Updated')
        ->and(BlockItems::summary($edited, $schema)['scenes'][1]['children'])->toBe([['id' => 'heading', 'type' => 'heading']]);
    expect(fn () => BlockItems::apply($edited, $schema, ['op' => 'update_item', 'target' => $target, 'values' => ['children' => []]]))
        ->toThrow(ValidationException::class);
});
