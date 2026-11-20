<?php

declare(strict_types=1);

namespace DbCms\Core\Search\Blocks;

use DbCms\Core\Blocks\AbstractBlockType;
use DbCms\Core\Models\Market;

/**
 * Search box block.
 *
 * Drops the general search into any page, post or landing composed with the
 * block manager. It is a plain GET form: the query is consolidated by a 301 into
 * the canonical friendly search URL, so the page keeps working without
 * JavaScript and no query string is ever left in the address bar.
 */
class SearchBoxBlock extends AbstractBlockType
{
    public function getTranslationPaths(): array
    {
        return ['title', 'placeholder'];
    }

    public function getType(): string
    {
        return 'search_box';
    }

    public function getName(): string
    {
        return 'Buscador general';
    }

    public function getIcon(): string
    {
        return 'fa-solid fa-magnifying-glass';
    }

    public function getCategory(): string
    {
        return 'dynamic';
    }

    public function getDescription(): string
    {
        return 'Caja de búsqueda sobre todas las entidades instaladas, con URL amigable e indexación controlada desde Filtros y búsquedas.';
    }

    public function getRequiredModule(): ?string
    {
        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function getDefaultPayload(): array
    {
        return [
            'title' => '¿Qué estás buscando?',
            'size' => 'lg',
            'placeholder' => 'Busca servicios, artículos o productos…',
        ];
    }

    /** @return array<string, mixed> */
    public function getExamplePayload(string $locale): array
    {
        if (str_starts_with(strtolower($locale), 'es')) {
            return $this->getDefaultPayload();
        }

        return array_replace($this->getDefaultPayload(), ['title' => 'What are you looking for?', 'placeholder' => 'Search services, articles or products…']);
    }

    /**
     * @return array<string, mixed>
     */
    public function getEditorSchema(): array
    {
        return [
            'title' => ['type' => 'text', 'label' => 'Título', 'description' => 'Título opcional sobre la caja de búsqueda.', 'rules' => ['nullable', 'string', 'max:255']],
            'size' => ['type' => 'select', 'label' => 'Tamaño', 'options' => ['md' => 'Normal', 'lg' => 'Grande'], 'default' => 'lg', 'description' => 'Tamaño visual de la caja de búsqueda.'],
            'placeholder' => ['type' => 'text', 'label' => 'Texto de ayuda', 'description' => 'Texto que aparece dentro del campo vacío.', 'rules' => ['nullable', 'string', 'max:255']],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function render(array $payload, ?Market $market = null, ?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();
        $title = trim((string) ($payload['title'] ?? ''));
        $size = (string) ($payload['size'] ?? 'md') === 'lg' ? 'lg' : 'md';
        $placeholder = trim((string) ($payload['placeholder'] ?? ''));

        $html = '';

        if ($title !== '') {
            $html .= '<h2 class="text-lg font-bold tracking-tight mb-3">'.e($title).'</h2>';
        }

        $html .= view('components.cms-search.box', [
            'size' => $size,
            'placeholder' => $placeholder !== '' ? $placeholder : null,
        ])->render();

        return '<div class="cms-search-block my-8 max-w-2xl mx-auto">'.$html.'</div>';
    }
}
