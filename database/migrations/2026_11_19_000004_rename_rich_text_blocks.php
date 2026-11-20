<?php

declare(strict_types=1);

use DbCms\Core\Content\Contracts\EditorialWorkflowInterface;
use DbCms\Core\Content\Contracts\EntitySchemaRegistryInterface;
use DbCms\Core\Contracts\Sectionable;
use DbCms\Core\Models\FilterPage;
use DbCms\Core\Sections\RichTextSectionUpgrade;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $upgrade = app(RichTextSectionUpgrade::class);
        $workflow = app(EditorialWorkflowInterface::class);
        $owners = [FilterPage::class, ...array_column(app(EntitySchemaRegistryInterface::class)->all(), 'model')];
        $storage = [];
        foreach (array_unique($owners) as $class) {
            $owner = new $class;
            if ($owner instanceof Model && $owner instanceof Sectionable && method_exists($owner, 'sections')) {
                $storage[(new ($owner->sectionModelClass()))->getTable()] = $owner;
            }
        }
        $this->assertKnownStorage(array_keys($storage));

        DB::transaction(function () use ($storage, $upgrade, $workflow): void {
            foreach ($storage as $table => $owner) {
                if (! Schema::hasTable($table)) {
                    continue;
                }
                $relation = (new ($owner->sectionModelClass()))->translations();
                $translations = $relation->getRelated()->getTable();
                $sectionKey = $relation->getForeignKeyName();
                DB::table($table)->lockForUpdate()->chunkById(100, function ($sections) use ($table, $owner, $translations, $sectionKey, $upgrade, $workflow): void {
                    $localized = DB::table($translations)->whereIn($sectionKey, $sections->pluck('id'))
                        ->lockForUpdate()->get()->groupBy($sectionKey);
                    foreach ($sections as $section) {
                        $record = [
                            'attributes' => (array) $section,
                            'translations' => $localized->get($section->id, collect())->map(static fn (object $row): array => (array) $row)->all(),
                        ];
                        $next = $upgrade->record($record);
                        if ($next === $record) {
                            continue;
                        }

                        $entityId = $record['attributes'][$owner->sectionForeignKey()];
                        $entity = $owner->newQueryWithoutScopes()->find($entityId);
                        if ($entity !== null && $workflow->pendingDraft($entity) !== null) {
                            throw new RuntimeException('Resolve the pending editorial revision before upgrading Rich Text on '.$owner->getTable().' #'.$entityId.'.');
                        }
                        $this->update($table, $section->id, $record['attributes'], $next['attributes']);
                        foreach ($next['translations'] as $i => $row) {
                            $this->update($translations, $row['id'], $record['translations'][$i], $row);
                        }
                    }
                });
            }
        });
    }

    /** @param list<string> $known */
    private function assertKnownStorage(array $known): void
    {
        foreach (Schema::getTables() as $table) {
            $name = $table['name'];
            if (! in_array($name, $known, true) && Schema::hasColumns($name, ['type', 'styling_json'])
                && DB::table($name)->exists()) {
                throw new RuntimeException('Register the section owner for '.$name.' before upgrading Rich Text.');
            }
        }
    }

    private function update(string $table, int $id, array $before, array $after): void
    {
        $changes = array_filter($after, static fn (mixed $value, string $key): bool => $value !== ($before[$key] ?? null), ARRAY_FILTER_USE_BOTH);
        if ($changes !== []) {
            DB::table($table)->where('id', $id)->update($changes);
        }
    }

    public function down(): void
    {
        throw new LogicException('Restore the pre-upgrade database backup to roll back the Rich Text migration.');
    }
};
