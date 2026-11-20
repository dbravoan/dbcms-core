<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Seo;

use DbCms\Core\Content\EntitySchema;
use DbCms\Core\Contracts\Services\PublicContentDiscoveryInterface;
use DbCms\Core\Contracts\Services\PublishedSectionTextInterface;
use DbCms\Core\Contracts\Services\SeoAuditReportServiceInterface;
use DbCms\Core\DTOs\Seo\SeoAuditReportDTO;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;

/** Local editorial checks against publicly visible entities and published sections. */
final class SeoAuditReportService implements SeoAuditReportServiceInterface
{
    public function __construct(
        private readonly PublicContentDiscoveryInterface $discovery,
        private readonly ContentQualityAuditorService $auditor,
        private readonly JsonLdSchemaService $schema,
        private readonly PublishedSectionTextInterface $sections,
    ) {}

    public function report(): SeoAuditReportDTO
    {
        $markets = $this->discovery->markets();
        $audited = [];
        $counts = [];
        foreach ($this->discovery->schemas() as $schema) {
            $ids = [];
            $auditedIds = [];
            foreach ($markets as $market) {
                foreach ($market->languages as $language) {
                    foreach ($this->discovery->query($schema->type, $market, $language->code)->latest()->get() as $entity) {
                        $ids[$entity->getKey()] = true;
                        if (count($auditedIds) >= 10 || isset($auditedIds[$entity->getKey()])) {
                            continue;
                        }
                        $translation = $entity->getRelation('translations')->firstWhere('locale', $language->code);
                        if (! $translation instanceof Model) {
                            continue;
                        }
                        $title = $this->field($schema, $translation, EntitySchema::ROLE_TITLE);
                        $content = $this->field($schema, $translation, EntitySchema::ROLE_BODY);
                        if ($schema->sectionable) {
                            $content .= "\n".$this->sections->text($entity, $market, $language->code);
                        }
                        $audit = $this->auditor->audit($title, $content, $this->field($schema, $translation, EntitySchema::ROLE_META_DESCRIPTION));
                        $audited[] = ['id' => $entity->getKey(), 'title' => $title, 'slug' => (string) $translation->getAttribute('slug'), 'locale' => $language->code, 'score' => $audit['overall_score'], 'recommendations' => $audit['recommendations'], 'word_count' => $audit['metrics']['word_count'] ?? 0, 'type' => $schema->label];
                        $auditedIds[$entity->getKey()] = true;
                    }
                }
            }
            $counts[$schema->type] = ['label' => $schema->label, 'count' => count($ids)];
        }

        return new SeoAuditReportDTO($audited, $counts, $markets, (string) json_encode($this->schema->generateSiteGraph(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), Route::has('geo.llms') && Route::has('geo.llms-full'));
    }

    private function field(EntitySchema $schema, Model $translation, string $role): string
    {
        $input = $schema->fields[$role]['input'] ?? '';
        if (preg_match('/\[([^\[\]]+)\]$/', $input, $match) !== 1) {
            return '';
        }

        return (string) ($translation->getAttribute($match[1]) ?? '');
    }
}
