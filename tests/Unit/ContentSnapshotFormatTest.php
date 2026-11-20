<?php

declare(strict_types=1);

namespace Tests\Unit;

use DbCms\Core\Content\Contracts\EntitySchemaRegistryInterface;
use DbCms\Core\Services\Capabilities\ContentSnapshotService;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\TestCase;

final class ContentSnapshotFormatTest extends TestCase
{
    public function test_current_snapshots_contain_only_canonical_record_collections(): void
    {
        $schemas = $this->createMock(EntitySchemaRegistryInterface::class);
        $schemas->method('all')->willReturn([]);
        $service = new ContentSnapshotService($schemas);
        $entity = new class extends Model {};
        $entity->setRawAttributes(['id' => 7, 'title' => 'Current']);
        self::assertSame(['snapshot_version', 'attributes', 'translation_records', 'section_records', 'market_ids', 'market_pivots'], array_keys($service->capture($entity)));
    }

    public function test_editing_refuses_historical_or_incomplete_snapshots(): void
    {
        $service = new ContentSnapshotService($this->createMock(EntitySchemaRegistryInterface::class));
        $entity = new class extends Model {};
        $this->expectException(DomainException::class);
        $service->hydrate($entity, ['snapshot_version' => 2, 'attributes' => ['id' => 7], 'translations' => ['es' => ['title' => 'Historical']]]);
    }

    public function test_restoring_refuses_an_incomplete_snapshot_before_any_write(): void
    {
        $service = new ContentSnapshotService($this->createMock(EntitySchemaRegistryInterface::class));
        $entity = new class extends Model {};
        $this->expectException(DomainException::class);
        $service->restore($entity, ['snapshot_version' => 3, 'attributes' => ['id' => 7]]);
    }

    public function test_incomplete_nested_records_are_rejected_before_restore_can_write(): void
    {
        $service = new ContentSnapshotService($this->createMock(EntitySchemaRegistryInterface::class));
        $entity = new class extends Model {};
        $this->expectException(DomainException::class);
        $service->restore($entity, [
            'snapshot_version' => 3,
            'attributes' => ['id' => 7],
            'translation_records' => [],
            'section_records' => [['attributes' => ['id' => 9]]],
            'market_ids' => [],
            'market_pivots' => [],
        ]);
    }
}
