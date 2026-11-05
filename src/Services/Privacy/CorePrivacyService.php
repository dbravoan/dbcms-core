<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Privacy;

use DbCms\Core\Models\SystemNotification;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/** Retention for core-owned operational records; business owners keep their own policies. */
final class CorePrivacyService
{
    /** @return array<string,int> */
    public function prune(): array
    {
        $policy = app(LegalPageService::class)->requireProcessing('core');
        $days = (int) $policy['retention_days'];
        $cutoff = now()->subDays($days);
        $expired = [
            'local_daily_logs' => $this->pruneLocalDailyLogs($days),
            'consents' => DB::table('privacy_consents')->where('expires_at', '<=', now())->delete(),
            'sessions' => DB::table('sessions')->where('last_activity', '<=', now()->subMinutes((int) config('session.lifetime'))->timestamp)->delete(),
            'password_resets' => DB::table('password_reset_tokens')->where('created_at', '<=', now()->subMinutes((int) config('auth.passwords.users.expire', 60)))->delete(),
            'media_migration_history' => DB::table('media_migration_runs')->whereIn('status', ['completed', 'failed'])->where('updated_at', '<=', $cutoff)->delete(),
            'capability_audit' => DB::table('capability_audit_logs')->where('created_at', '<=', $cutoff)->delete(),
        ];
        // Pending deliveries become harmless no-ops when their notification is removed.
        // SMTP currently in progress must finish or be reviewed before its evidence is erased.
        $expired['notifications'] = SystemNotification::query()->where('created_at', '<=', $cutoff)
            ->whereNotIn('id', DB::table('notification_email_deliveries')->select('notification_id')->where('status', 'sending'))->delete();
        $expired['queued_jobs_requiring_review'] = DB::table('jobs')->where('created_at', '<=', $cutoff->timestamp)->count();
        $expired['batches_requiring_review'] = DB::table('job_batches')->whereNull('finished_at')->whereNull('cancelled_at')->where('created_at', '<=', $cutoff->timestamp)->count();
        $expired['media_migrations_requiring_review'] = DB::table('media_migration_runs')->whereIn('status', ['pending', 'running'])->where('updated_at', '<=', $cutoff)->count();
        $expired['deliveries_requiring_review'] = DB::table('notification_email_deliveries')->where('status', 'sending')->where('created_at', '<=', $cutoff)->count();
        foreach ([
            ['passport:purge', ['--hours' => 0]],
            ['queue:prune-failed', ['--hours' => $days * 24]],
            ['queue:prune-batches', ['--hours' => $days * 24, '--cancelled' => $days * 24]],
        ] as [$command, $arguments]) {
            if (Artisan::call($command, $arguments) !== 0) {
                throw new \RuntimeException('Core privacy maintenance failed: '.$command);
            }
        }

        return $expired;
    }

    /** Only the standard core log files under the ordinary storage/logs directory. */
    private function pruneLocalDailyLogs(int $days): int
    {
        $directory = storage_path('logs');
        if (! is_dir($directory)) {
            return 0;
        }
        if (is_link($directory)) {
            throw new \RuntimeException('Core log retention cannot follow a symbolic-link log directory.');
        }
        $cutoff = now()->subDays($days)->startOfDay()->timestamp;
        $count = 0;
        foreach (new \DirectoryIterator($directory) as $file) {
            if ($file->isLink() || ! $file->isFile() || ! preg_match('/\Alaravel-(\d{4}-\d{2}-\d{2})\.log\z/', $file->getFilename(), $matches)) {
                continue;
            }
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $matches[1]);
            if ($date === false || $date->format('Y-m-d') !== $matches[1] || $date->getTimestamp() >= $cutoff) {
                continue;
            }
            if (! unlink($file->getPathname())) {
                throw new \RuntimeException('Could not remove an expired core daily log.');
            }
            $count++;
        }

        return $count;
    }
}
