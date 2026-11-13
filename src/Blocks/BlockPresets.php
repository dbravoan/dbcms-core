<?php

declare(strict_types=1);

namespace DbCms\Core\Blocks;

use DbCms\Core\Blocks\Contracts\BlockRegistryInterface;
use Illuminate\Validation\ValidationException;

/** Templates expand to ordinary editable nodes; they are never special renderers. */
final class BlockPresets
{
    public function __construct(private readonly BlockRegistryInterface $blocks) {}

    /** @return array<string,array<string,mixed>> */
    private function definitions(): array
    {
        $testimonial = ['type' => 'container', 'layout' => 'stack', 'children' => [
            ['type' => 'quote', 'quote' => '', 'author' => ''],
            ['type' => 'image', 'image_url' => '', 'image_alt' => ''],
            ['type' => 'text', 'content' => '', 'format' => 'markdown'],
            ['type' => 'rating', 'value' => 0],
        ]];
        $plan = ['type' => 'container', 'layout' => 'stack', 'children' => [
            ['type' => 'heading', 'text' => '', 'level' => 'h3'], ['type' => 'price', 'amount' => ''],
            ['type' => 'list', 'items' => []], ['type' => 'button', 'label' => '', 'url' => ''],
        ]];

        return [
            'faq' => ['name' => 'Preguntas frecuentes', 'description' => 'Acordeón con respuestas de contenido libre.', 'node' => ['type' => 'accordion', 'items' => [['title' => '', 'children' => [['type' => 'text', 'content' => '', 'format' => 'markdown']]]]]],
            'plans' => ['name' => 'Comparativa de planes', 'description' => 'Tarjetas de precios independientes y editables.', 'node' => ['type' => 'container', 'layout' => 'grid', 'grid_columns' => 3, 'children' => [$plan, $plan, $plan]]],
            'testimonial' => ['name' => 'Testimonio', 'description' => 'Cita, imagen, texto y valoración opcional.', 'node' => $testimonial],
            'testimonials-carousel' => ['name' => 'Carrusel de testimonios', 'description' => 'Composiciones editables dentro de un carrusel genérico.', 'node' => ['type' => 'carousel', 'items' => [['children' => [$testimonial]], ['children' => [$testimonial]]]]],
            'gallery' => ['name' => 'Galería de imágenes', 'description' => 'Cuadrícula de imágenes de tu biblioteca.', 'node' => ['type' => 'container', 'layout' => 'grid', 'grid_columns' => 3, 'children' => [['type' => 'image', 'image_url' => '', 'image_alt' => '']]]],
            'cta' => ['name' => 'Llamada a la acción', 'description' => 'Título, texto y botón sin estructura rígida.', 'node' => ['type' => 'container', 'layout' => 'stack', 'children' => [['type' => 'heading', 'text' => ''], ['type' => 'text', 'content' => '', 'format' => 'markdown'], ['type' => 'button', 'label' => '', 'url' => '']]]],
        ];
    }

    /** @return list<array<string,mixed>> */
    public function listing(): array
    {
        $items = [];
        foreach ($this->definitions() as $id => $definition) {
            if ($this->available($definition['node'])) {
                $items[] = ['id' => $id, 'name' => $definition['name'], 'description' => $definition['description']];
            }
        }

        return $items;
    }

    /** @return array<string,mixed> */
    public function expand(string $id): array
    {
        $definition = $this->definitions()[$id] ?? null;
        if ($definition === null || ! $this->available($definition['node'])) {
            throw ValidationException::withMessages(['preset' => 'La composición no está disponible.']);
        }

        return $this->defaults($definition['node']);
    }

    /** @param array<string,mixed> $node */
    private function available(array $node): bool
    {
        foreach (app(BlockTree::class)->nodes($node) as $child) {
            if (! $this->blocks->isAvailable((string) ($child['type'] ?? 'text'))) {
                return false;
            }
        }

        return true;
    }

    /** @param array<string,mixed> $node
     * @return array<string,mixed> */
    private function defaults(array $node): array
    {
        $node = array_replace($this->blocks->get((string) $node['type'])?->getDefaultPayload() ?? [], $node);
        $node = app(BlockTree::class)->mapSlots($node, fn (array $children): array => array_map($this->defaults(...), $children));

        return app(BlockTree::class)->identify($node);
    }
}
