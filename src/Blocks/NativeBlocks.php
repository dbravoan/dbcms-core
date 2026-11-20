<?php

declare(strict_types=1);

namespace DbCms\Core\Blocks;

use DbCms\Core\Blocks\Contracts\BlockTypeInterface;

/** Built-in primitives have no package activation state. */
final class NativeBlocks
{
    /** @var list<class-string<BlockTypeInterface>> */
    public const TYPES = [
        Types\MapBlock::class,
        Types\SpacerBlock::class,
        Types\QuoteBlock::class,
        Types\ImageBlock::class,
        Types\PlaylistBlock::class,
        Types\HeadingBlock::class,
        Types\AlertBlock::class,
        Types\ButtonBlock::class,
        Types\AccordionBlock::class,
        Types\PriceBlock::class,
        Types\LottieBlock::class,
        Types\RatingBlock::class,
        Types\CounterBlock::class,
        Types\TabsBlock::class,
        Types\CarouselBlock::class,
        Types\ListBlock::class,
        Types\HotspotsBlock::class,
        Types\CountdownBlock::class,
        Types\IconBlock::class,
        Types\CodeEmbedBlock::class,
        Types\VideoBlock::class,
        Types\DrawerBlock::class,
        Types\ProgressBlock::class,
        Types\SeparatorBlock::class,
        Types\RichTextBlock::class,
        Types\KpiBlock::class,
        Types\RoiBlock::class,
        Types\ChartsBlock::class,
    ];
}
