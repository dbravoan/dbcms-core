<?php

declare(strict_types=1);

namespace DbCms\Core\Blocks\Types;

use DbCms\Core\Blocks\Contracts\BlockPayloadCompatibilityInterface;

final class CarouselBlock extends ComposableBlock implements BlockPayloadCompatibilityInterface
{
    protected const TYPE = 'carousel';

    protected const NAME = 'Carrusel';

    protected const ICON = 'fa-solid fa-images';

    protected const CATEGORY = 'interactive';

    protected const DEFAULTS = ['label' => '', 'autoplay' => false, 'interval' => 6000, 'loop' => false, 'show_arrows' => true, 'show_dots' => true, 'slides_per_view' => 1, 'slides_per_view_tablet' => 1, 'slides_per_view_mobile' => 1, 'slides_per_group' => 1, 'slides_per_group_tablet' => 1, 'slides_per_group_mobile' => 1, 'gap' => 24, 'items' => []];

    protected const EXAMPLE = ['label' => 'Proyectos destacados', 'items' => [
        ['label' => 'Clínica Aurora', 'children' => [['type' => 'image', 'image_url' => '/images/placeholder.svg', 'image_alt' => 'Recepción luminosa de una clínica', 'fit' => 'cover'], ['type' => 'heading', 'text' => 'Clínica Aurora', 'level' => 'h3'], ['type' => 'rich_text', 'content' => 'Nueva web con reserva de citas online: un 38 % más de solicitudes en tres meses.', 'format' => 'markdown']]],
        ['label' => 'Bodegas Lumbre', 'children' => [['type' => 'image', 'image_url' => '/images/placeholder.svg', 'image_alt' => 'Viñedo al atardecer', 'fit' => 'cover'], ['type' => 'heading', 'text' => 'Bodegas Lumbre', 'level' => 'h3'], ['type' => 'rich_text', 'content' => 'Tienda online en tres idiomas con envíos a toda la Unión Europea.', 'format' => 'markdown']]],
        ['label' => 'Estudio Norte', 'children' => [['type' => 'image', 'image_url' => '/images/placeholder.svg', 'image_alt' => 'Equipo de arquitectura revisando planos', 'fit' => 'cover'], ['type' => 'heading', 'text' => 'Estudio Norte', 'level' => 'h3'], ['type' => 'rich_text', 'content' => 'Portfolio visual que se actualiza desde el panel en minutos.', 'format' => 'markdown']]],
    ]];

    protected const EXAMPLE_EN = ['label' => 'Featured projects', 'items' => [
        ['label' => 'Aurora Clinic', 'children' => [['type' => 'image', 'image_url' => '/images/placeholder.svg', 'image_alt' => 'Bright reception area of a clinic', 'fit' => 'cover'], ['type' => 'heading', 'text' => 'Aurora Clinic', 'level' => 'h3'], ['type' => 'rich_text', 'content' => 'New website with online booking: 38% more requests in three months.', 'format' => 'markdown']]],
        ['label' => 'Lumbre Winery', 'children' => [['type' => 'image', 'image_url' => '/images/placeholder.svg', 'image_alt' => 'Vineyard at sunset', 'fit' => 'cover'], ['type' => 'heading', 'text' => 'Lumbre Winery', 'level' => 'h3'], ['type' => 'rich_text', 'content' => 'Online shop in three languages shipping across the European Union.', 'format' => 'markdown']]],
        ['label' => 'Norte Studio', 'children' => [['type' => 'image', 'image_url' => '/images/placeholder.svg', 'image_alt' => 'Architecture team reviewing plans', 'fit' => 'cover'], ['type' => 'heading', 'text' => 'Norte Studio', 'level' => 'h3'], ['type' => 'rich_text', 'content' => 'Visual portfolio updated from the panel in minutes.', 'format' => 'markdown']]],
    ]];

    protected const TRANSLATABLE = ['label', 'items.*.label'];

    public function retiredPayloadKeys(): array
    {
        return ['slides'];
    }

    public function getEditorSchema(): array
    {
        return self::withDefaults([
            'label' => self::text('Etiqueta accesible'), 'autoplay' => self::boolean('Reproducción automática'),
            'interval' => self::number('Intervalo en milisegundos', 2000, 60000, true), 'loop' => self::boolean('Repetir'),
            'show_arrows' => self::boolean('Mostrar flechas'), 'show_dots' => self::boolean('Mostrar indicadores'),
            'slides_per_view' => self::number('Diapositivas visibles en escritorio', 1, 6, true),
            'slides_per_view_tablet' => self::number('Diapositivas visibles en tableta', 1, 6, true),
            'slides_per_view_mobile' => self::number('Diapositivas visibles en móvil', 1, 6, true),
            'slides_per_group' => self::number('Avance en escritorio', 1, 6, true),
            'slides_per_group_tablet' => self::number('Avance en tableta', 1, 6, true),
            'slides_per_group_mobile' => self::number('Avance en móvil', 1, 6, true),
            'gap' => self::number('Separación', 0, 96, true),
            'items' => self::items(['label' => self::text('Etiqueta de la diapositiva'), 'children' => self::childrenField()], 'Diapositivas'),
        ]);
    }
}
