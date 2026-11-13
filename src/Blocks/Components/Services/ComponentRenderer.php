<?php

declare(strict_types=1);

namespace DbCms\Core\Blocks\Components\Services;

use DbCms\Core\Blocks\BlockTree;
use DbCms\Core\Models\BlockComponent;
use DbCms\Core\Models\Market;
use DbCms\Core\Sections\SectionRendererService;
use DbCms\Core\Services\Content\PublishedSectionTextService;
use Illuminate\Validation\ValidationException;

/** Request-scoped stack supplements the shared BlockRenderer depth and payload guards. */
final class ComponentRenderer
{
    /** @var list<int> */
    private array $stack = [];

    public function __construct(private readonly PublicComponentReader $reader, private readonly ComponentProperties $properties, private readonly SectionRendererService $sections, private readonly BlockTree $tree) {}

    /** @param array<string,mixed> $overrides */
    public function render(int $id, array $overrides, ?Market $market, string $locale): string
    {
        if ($market === null) {
            throw new \DomainException('El componente requiere un mercado público explícito.');
        }
        if (in_array($id, $this->stack, true)) {
            throw new \DomainException('Referencia circular entre componentes reutilizables.');
        }
        if (count($this->stack) >= (int) config('cms.blocks.max_depth', 16)) {
            throw new \DomainException('La composición supera la profundidad de componentes permitida.');
        }
        $component = $this->reader->find($id, $market, $locale);
        if ($component === null) {
            throw new \DomainException('Componente no disponible en este idioma y mercado.');
        }
        if ($this->stack === []) {
            $count = 0;
            $this->assertGraph($component, $market, $locale, [], $count);
        }
        $this->stack[] = $id;
        try {
            $rows = $this->properties->apply($this->reader->sections($component, $market, $locale), $component->exposed_properties ?? [], $overrides);

            return implode("\n", array_map(fn (array $section): string => $this->sections->renderSection($section, $market, $locale), $rows));
        } finally {
            array_pop($this->stack);
        }
    }

    /** Read-only semantic expansion: never executes Blade or widget rendering.
     * @param array<string,mixed> $overrides */
    public function text(int $id, array $overrides, Market $market, string $locale, bool $includeWidgets = true): string
    {
        if ($id < 1 || in_array($id, $this->stack, true) || count($this->stack) >= (int) config('cms.blocks.max_depth', 16)) {
            return '';
        }
        $component = $this->reader->find($id, $market, $locale);
        if ($component === null) {
            return '';
        }
        try {
            if ($this->stack === []) {
                $count = 0;
                $this->assertGraph($component, $market, $locale, [], $count);
            }
            $rows = $this->properties->apply($this->reader->sections($component, $market, $locale), $component->exposed_properties ?? [], $overrides);
        } catch (\DomainException|ValidationException) {
            return '';
        }
        $this->stack[] = $id;
        try {
            return implode("\n", array_map(fn (array $section): string => app(PublishedSectionTextService::class)->payloadText($section, $market, $locale, $includeWidgets), $rows));
        } finally {
            array_pop($this->stack);
        }
    }

    /** @param array<string,mixed> $overrides */
    public function validate(int $id, array $overrides, Market $market, string $locale): void
    {
        $component = $this->reader->find($id, $market, $locale);
        if ($component === null) {
            throw ValidationException::withMessages(['component_id' => 'Componente no disponible en este idioma y mercado.']);
        }
        $this->properties->apply($this->reader->sections($component, $market, $locale), $component->exposed_properties ?? [], $overrides);
        $count = 0;
        try {
            $this->assertGraph($component, $market, $locale, [], $count);
        } catch (\DomainException $error) {
            throw ValidationException::withMessages(['component_id' => $error->getMessage()]);
        }
    }

    /** Validates an edited definition against published dependencies before any section write.
     * @param list<array<string,mixed>> $sections */
    public function validateCandidate(int $id, array $sections, Market $market, string $locale): void
    {
        $count = 0;
        foreach ($sections as $section) {
            foreach ($this->tree->nodes($section) as $node) {
                if (($node['type'] ?? null) !== 'component') {
                    continue;
                }
                $child = $this->reader->find((int) ($node['component_id'] ?? 0), $market, $locale);
                if ($child === null) {
                    throw ValidationException::withMessages(['sections_json' => 'Componente referenciado no disponible en este idioma y mercado.']);
                }
                try {
                    $this->assertGraph($child, $market, $locale, [$id], $count);
                } catch (\DomainException $error) {
                    throw ValidationException::withMessages(['sections_json' => $error->getMessage()]);
                }
            }
        }
    }

    /** @param list<int> $path */
    private function assertGraph(BlockComponent $component, Market $market, string $locale, array $path, int &$count): void
    {
        if (in_array($component->id, $path, true)) {
            throw new \DomainException('Referencia circular entre componentes reutilizables.');
        }
        if (count($path) >= (int) config('cms.blocks.max_depth', 16)) {
            throw new \DomainException('La composición supera la profundidad de componentes permitida.');
        }
        $path[] = $component->id;
        $sections = $this->reader->sections($component, $market, $locale);
        $this->properties->validate($component->exposed_properties ?? [], $sections);
        foreach ($sections as $section) {
            foreach ($this->tree->nodes($section) as $node) {
                if (++$count > (int) config('cms.blocks.max_nodes', 1000)) {
                    throw new \DomainException('La expansión de componentes contiene demasiados bloques.');
                }
                if (($node['type'] ?? null) !== 'component') {
                    continue;
                }
                $child = $this->reader->find((int) ($node['component_id'] ?? 0), $market, $locale);
                if ($child === null) {
                    throw new \DomainException('Componente referenciado no disponible en este idioma y mercado.');
                }
                $this->properties->apply($this->reader->sections($child, $market, $locale), $child->exposed_properties ?? [], (array) ($node['overrides'] ?? []));
                $this->assertGraph($child, $market, $locale, $path, $count);
            }
        }
    }
}
