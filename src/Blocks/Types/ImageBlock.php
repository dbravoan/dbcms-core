<?php

declare(strict_types=1);

namespace DbCms\Core\Blocks\Types;

final class ImageBlock extends ComposableBlock
{
    protected const TYPE = 'image';

    protected const NAME = 'Imagen';

    protected const ICON = 'fa-solid fa-image';

    protected const CATEGORY = 'media';

    protected const DEFAULTS = ['image_url' => '', 'image_alt' => '', 'caption' => '', 'fit' => 'cover', 'width' => 0, 'height' => 0, 'link_url' => ''];

    protected const EXAMPLE = ['image_url' => '/images/placeholder.svg', 'image_alt' => 'Paisaje de montaña con el sol sobre la cumbre', 'caption' => 'Ruta de los Tres Lagos, Picos de Europa.', 'width' => 1200, 'height' => 800];

    protected const EXAMPLE_EN = ['image_url' => '/images/placeholder.svg', 'image_alt' => 'Mountain landscape with the sun over the summit', 'caption' => 'Three Lakes trail, Picos de Europa.', 'width' => 1200, 'height' => 800];

    protected const TRANSLATABLE = ['image_alt', 'caption'];

    public function getEditorSchema(): array
    {
        return self::withDefaults(['image_url' => self::media('Imagen') + ['alt_key' => 'image_alt'], 'image_alt' => self::text('Descripción accesible', 1000), 'caption' => self::text('Pie de imagen', 2000, 'textarea'), 'fit' => self::select('Ajuste', ['cover' => 'Cubrir', 'contain' => 'Contener']), 'width' => self::number('Anchura intrínseca', 0, 16000, true), 'height' => self::number('Altura intrínseca', 0, 16000, true), 'link_url' => self::text('Destino opcional', 2048, 'url')]);
    }
}
