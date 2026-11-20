<?php

declare(strict_types=1);

namespace DbCms\Core\Forms\Services;

use DbCms\Core\Forms\Models\FormAttachment;
use DbCms\Core\Forms\Models\FormSubmission;
use DbCms\Core\Services\Privacy\LegalPageService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** Operates on explicitly identified records, including retained data from inactive forms. */
final class FormPrivacyService
{
    /** @param list<int> $ids
     * @return list<array<string,mixed>>
     */
    public function export(array $ids): array
    {
        return array_values(FormSubmission::query()->with('attachments', 'actions')->whereIn('id', $ids)->orderBy('id')->get()->map(static function (FormSubmission $submission): array {
            $data = $submission->toArray();
            $data['attachments'] = $submission->attachments->map(static fn ($attachment): array => $attachment->toArray() + ['content_base64' => base64_encode($attachment->bytes())])->all();

            return $data;
        })->all());
    }

    /** @param list<int> $ids */
    public function erase(array $ids): int
    {
        return DB::transaction(static function () use ($ids): int {
            $submissions = FormSubmission::query()->whereIn('id', $ids)->get();
            $submissions->each(static fn (FormSubmission $submission) => $submission->delete());

            return $submissions->count();
        });
    }

    /** Removes interrupted/rolled-back uploads without touching in-flight files. */
    private function pruneOrphanFiles(): void
    {
        $disk = Storage::disk('local');
        foreach ($disk->allFiles('forms') as $path) {
            if ($disk->lastModified($path) <= now()->subDay()->getTimestamp()
                && ! FormAttachment::query()->where('path', $path)->exists()) {
                $disk->delete($path);
            }
        }
    }

    public function prune(): int
    {
        $policy = app(LegalPageService::class)->requireProcessing('forms');

        $this->pruneOrphanFiles();

        return $this->erase(array_values(array_map('intval', FormSubmission::query()->whereDoesntHave('actions', static fn ($query) => $query->where('status', 'processing'))->where('created_at', '<=', now()->subDays((int) $policy['retention_days']))->pluck('id')->all())));
    }
}
