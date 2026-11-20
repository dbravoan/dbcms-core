<?php

declare(strict_types=1);

namespace DbCms\Core\Blocks\Types;

use DbCms\Core\Blocks\Contracts\BlockPayloadCompatibilityInterface;

final class HotspotsBlock extends ComposableBlock implements BlockPayloadCompatibilityInterface
{
    protected const TYPE = 'hotspots';

    protected const NAME = 'Puntos interactivos';

    protected const ICON = 'fa-solid fa-location-dot';

    protected const CATEGORY = 'interactive';

    protected const DEFAULTS = ['image_url' => '', 'image_alt' => '', 'items' => []];

    protected const EXAMPLE = ['image_url' => '/images/placeholder.svg', 'image_alt' => 'Paisaje de montaña al amanecer', 'items' => [
        ['label' => 'Refugio', 'x' => 33, 'y' => 45, 'text' => 'Refugio guardado abierto de junio a septiembre, con 40 plazas.'],
        ['label' => 'Mirador', 'x' => 54, 'y' => 62, 'text' => 'Mirador accesible a 20 minutos del aparcamiento.'],
        ['label' => 'Cumbre', 'x' => 75, 'y' => 22, 'text' => 'Cumbre a 2.430 m; ruta de dificultad media.'],
    ]];

    protected const EXAMPLE_EN = ['image_url' => '/images/placeholder.svg', 'image_alt' => 'Mountain landscape at sunrise', 'items' => [
        ['label' => 'Hut', 'x' => 33, 'y' => 45, 'text' => 'Staffed mountain hut open from June to September, with 40 beds.'],
        ['label' => 'Viewpoint', 'x' => 54, 'y' => 62, 'text' => 'Accessible viewpoint 20 minutes from the car park.'],
        ['label' => 'Summit', 'x' => 75, 'y' => 22, 'text' => 'Summit at 2,430 m; moderate route.'],
    ]];

    protected const TRANSLATABLE = ['image_alt', 'items.*.label', 'items.*.text'];

    public function retiredPayloadKeys(): array
    {
        return ['children'];
    }

    public function getEditorSchema(): array
    {
        return self::withDefaults([
            'image_url' => self::media('Imagen') + ['alt_key' => 'image_alt'],
            'image_alt' => self::text('Descripción accesible', 1000),
            'items' => self::items([
                'label' => self::text('Etiqueta del punto'),
                'x' => self::number('Posición horizontal (%)', 0, 100),
                'y' => self::number('Posición vertical (%)', 0, 100),
                'text' => self::text('Texto del punto', 2000, 'textarea'),
            ], 'Puntos'),
        ]);
    }
}
