<?php

declare(strict_types=1);

namespace DbCms\Core\Blocks\Types;

final class LottieBlock extends ComposableBlock
{
    protected const TYPE = 'lottie';

    protected const NAME = 'Animación Lottie';

    protected const ICON = 'fa-solid fa-wand-magic-sparkles';

    protected const CATEGORY = 'media';

    protected const DEFAULTS = ['animation_url' => '', 'poster_url' => '', 'label' => '', 'loop' => true, 'autoplay' => false, 'speed' => 1, 'direction' => 'forward'];

    protected const EXAMPLE = ['poster_url' => '/images/placeholder.svg', 'label' => 'Ilustración animada de un paisaje de montaña'];

    protected const EXAMPLE_EN = ['poster_url' => '/images/placeholder.svg', 'label' => 'Animated illustration of a mountain landscape'];

    protected const TRANSLATABLE = ['label'];

    public function getEditorSchema(): array
    {
        return self::withDefaults(['animation_url' => self::media('Archivo JSON de la biblioteca', 'animation') + ['description' => 'Animación vectorial local sin expresiones ni recursos externos; admite imágenes PNG, JPEG o WebP incrustadas.'], 'poster_url' => self::media('Imagen alternativa'), 'label' => self::text('Descripción accesible', 1000), 'loop' => self::boolean('Repetir'), 'autoplay' => self::boolean('Reproducir al ser visible'), 'direction' => self::select('Dirección', ['forward' => 'Hacia delante', 'reverse' => 'Hacia atrás']), 'speed' => self::number('Velocidad', 0.1, 4)]);
    }
}
