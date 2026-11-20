<?php

declare(strict_types=1);

namespace Tests\Unit;

use DbCms\Core\Modules\ModuleManifestGraph;
use DomainException;
use PHPUnit\Framework\TestCase;

final class ModuleManifestGraphTest extends TestCase
{
    public function test_composer_tag_and_development_versions_are_accepted(): void
    {
        $tagged = $this->manifest('blog');
        $tagged['version'] = 'v1.0.0';
        $tagged['provider'] = 'Acme\\Publishing\\Provider';
        $development = $this->manifest('gallery');
        $development['version'] = 'dev-main';
        $graph = new ModuleManifestGraph([$tagged, $development], '1.0.0');
        self::assertSame(['blog', 'gallery'], $graph->ordered(['blog', 'gallery']));
    }

    public function test_selected_dependencies_are_sorted_before_dependents(): void
    {
        $graph = new ModuleManifestGraph([
            $this->manifest('social-publishing', ['creative-studio' => '^1.2']),
            $this->manifest('creative-studio', ['core' => '^1.3'], '1.2.4'),
            $this->manifest('blog'),
        ], '1.3.0');

        self::assertSame(['blog', 'creative-studio', 'social-publishing'], $graph->ordered(['social-publishing', 'blog', 'creative-studio']));
        self::assertSame([], $graph->ordered([]));
    }

    public function test_duplicate_identifiers_are_rejected_before_registration(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Duplicate module identifier');
        new ModuleManifestGraph([$this->manifest('blog'), $this->manifest('blog')], '1.3.0');
    }

    public function test_a_missing_dependency_is_not_silently_enabled(): void
    {
        $graph = new ModuleManifestGraph([$this->manifest('social', ['creative' => '^1.0'])], '1.3.0');
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('requires unavailable module [creative]');
        $graph->ordered(['social']);
    }

    public function test_a_dependency_on_disk_must_also_be_selected(): void
    {
        $graph = new ModuleManifestGraph([$this->manifest('social', ['creative' => '^1.0']), $this->manifest('creative')], '1.3.0');
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('requires selected module [creative]');
        $graph->ordered(['social']);
    }

    public function test_incompatible_dependency_versions_are_rejected(): void
    {
        $graph = new ModuleManifestGraph([$this->manifest('social', ['creative' => '^2.0']), $this->manifest('creative')], '1.3.0');
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('does not satisfy [^2.0]');
        $graph->ordered(['social', 'creative']);
    }

    public function test_incompatible_core_versions_are_rejected(): void
    {
        $graph = new ModuleManifestGraph([$this->manifest('blog', ['core' => '>=2.0'])], '1.3.0');
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Core version [1.3.0]');
        $graph->ordered(['blog']);
    }

    public function test_dependency_cycles_are_rejected(): void
    {
        $graph = new ModuleManifestGraph([$this->manifest('alpha', ['beta' => '*']), $this->manifest('beta', ['alpha' => '*'])], '1.3.0');
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Module dependency cycle');
        $graph->ordered(['alpha', 'beta']);
    }

    public function test_identifier_grammar_accepts_hyphens_and_underscores(): void
    {
        ModuleManifestGraph::validateManifest($this->manifest('creative-studio'));
        ModuleManifestGraph::validateManifest($this->manifest('ai_assistant'));
        $this->expectException(DomainException::class);
        ModuleManifestGraph::validateManifest($this->manifest('../outside'));
    }

    public function test_invalid_version_constraints_are_rejected(): void
    {
        $this->expectException(DomainException::class);
        ModuleManifestGraph::validateManifest($this->manifest('blog', ['core' => 'definitely not a version']));
    }

    public function test_deactivation_reports_only_selected_dependents(): void
    {
        $graph = new ModuleManifestGraph([$this->manifest('social', ['creative' => '^1.0']), $this->manifest('creative'), $this->manifest('blog')], '1.3.0');
        self::assertSame(['social'], $graph->dependents('creative', ['social', 'creative', 'blog']));
        self::assertSame([], $graph->dependents('creative', ['creative', 'blog']));
    }

    /** @param array<string, string> $requires
     * @return array<string, mixed>
     */
    private function manifest(string $identifier, array $requires = [], string $version = '1.0.0'): array
    {
        return ['identifier' => $identifier, 'name' => $identifier, 'version' => $version, 'provider' => 'Acme\\Fixture\\Provider', 'requires' => $requires];
    }
}
