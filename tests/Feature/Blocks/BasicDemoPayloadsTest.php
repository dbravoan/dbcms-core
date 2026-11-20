<?php

declare(strict_types=1);

use DbCms\Core\Blocks\Contracts\BlockRegistryInterface;
use DbCms\Core\Blocks\Contracts\BlockTypeInterface;
use DbCms\Core\Blocks\Types\ComposableBlock;

/** @return array<string, BlockTypeInterface> */
function basicDemoBlocks(): array
{
    $registry = app(BlockRegistryInterface::class);

    return array_filter($registry->all(), static fn (BlockTypeInterface $block): bool => $block instanceof ComposableBlock);
}

/** @param array<mixed> $value */
function demoTextValues(array $value): array
{
    $texts = [];
    array_walk_recursive($value, static function (mixed $item) use (&$texts): void {
        if (is_string($item) && trim($item) !== '') {
            $texts[] = $item;
        }
    });

    return $texts;
}

test('every basic block offers a non-empty demo payload that validates and keeps its schema keys', function (): void {
    $registry = app(BlockRegistryInterface::class);
    $blocks = basicDemoBlocks();
    expect(count($blocks))->toBeGreaterThan(20);
    foreach ($blocks as $type => $block) {
        $payload = $block->getDefaultPayload();
        expect($payload)->not->toBeEmpty($type);
        expect(json_encode($payload, JSON_THROW_ON_ERROR))->toBeString();
        $registry->validate($type, $payload);
        // The demo values never introduce keys outside the editor schema (payload format unchanged).
        expect(array_diff(array_keys($payload), array_keys($block->getEditorSchema())))->toBe([], $type);
    }
});

test('blocks whose 192c301 examples were emptied insert filled demo content again', function (string $type, string $key): void {
    $block = app(BlockRegistryInterface::class)->get($type);
    expect($block)->not->toBeNull();
    $payload = $block?->getDefaultPayload() ?? [];
    expect(demoTextValues($payload))->not->toBeEmpty($type)
        ->and(trim((string) data_get($payload, $key)))->not->toBe('', "{$type}.{$key}");
})->with([
    'code_embed' => ['code_embed', 'content'],
    'rich_text' => ['rich_text', 'content'],
    'video' => ['video', 'title'],
]);
