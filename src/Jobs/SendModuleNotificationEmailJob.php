<?php

declare(strict_types=1);

namespace DbCms\Core\Jobs;

use DbCms\Core\Contracts\Services\NotificationRecipientResolverInterface;
use DbCms\Core\Mail\ModuleNotificationMail;
use DbCms\Core\Models\NotificationEmailDelivery;
use DbCms\Core\Models\SystemNotification;
use DbCms\Core\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

final class SendModuleNotificationEmailJob implements ShouldQueue
{
    use Queueable;

    /** SMTP acceptance after a lost connection can be uncertain; do not retry automatically. */
    public int $tries = 1;

    public function __construct(public readonly int $deliveryId)
    {
        $this->afterCommit();
    }

    public function handle(NotificationRecipientResolverInterface $recipients): void
    {
        $claimed = NotificationEmailDelivery::whereKey($this->deliveryId)->where('status', 'pending')->update(['status' => 'sending', 'attempted_at' => now()]);
        if ($claimed !== 1) {
            return;
        }
        $delivery = NotificationEmailDelivery::findOrFail($this->deliveryId);
        $notification = SystemNotification::find($delivery->notification_id);
        $user = $delivery->user_id === null ? null : User::find($delivery->user_id);
        try {
            if ($notification === null || $user === null || ! $recipients->allows($notification, $user)) {
                $delivery->update(['status' => 'skipped']);

                return;
            }
            if (! $this->hasRealTransport((string) config('mail.default'))) {
                throw new \RuntimeException('A real email transport is not configured.');
            }
            Mail::to($user->email)->send((new ModuleNotificationMail($notification))->locale($user->locale ?? app()->getLocale()));
            $delivery->update(['status' => 'sent', 'sent_at' => now()]);
        } catch (\Throwable $error) {
            $delivery->update(['status' => 'failed', 'failure_code' => $error::class]);
            Log::warning('Module notification mail failed', ['delivery_id' => $delivery->id, 'exception' => $error::class]);
            throw $error;
        }
    }

    /** @param list<string> $visited */
    private function hasRealTransport(string $mailer, array $visited = []): bool
    {
        if (in_array($mailer, $visited, true) || count($visited) > 8) {
            return false;
        }
        $transport = config('mail.mailers.'.$mailer.'.transport');
        if (! is_string($transport) || $transport === '' || in_array($transport, ['log', 'array'], true)) {
            return false;
        }
        if (in_array($transport, ['failover', 'roundrobin'], true)) {
            $members = config('mail.mailers.'.$mailer.'.mailers');
            if (! is_array($members) || $members === []) {
                return false;
            }
            foreach ($members as $member) {
                if (! is_string($member) || ! $this->hasRealTransport($member, [...$visited, $mailer])) {
                    return false;
                }
            }
        }

        return true;
    }
}
