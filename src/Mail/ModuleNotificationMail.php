<?php

declare(strict_types=1);

namespace DbCms\Core\Mail;

use DbCms\Core\Models\SystemNotification;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

final class ModuleNotificationMail extends Mailable
{
    public function __construct(public readonly SystemNotification $notification) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: str_replace(["\r", "\n"], ' ', $this->notification->title));
    }

    public function content(): Content
    {
        // Private metadata and destinations are deliberately absent from mail.
        // Opening the CMS requires the current actor's session and permissions.
        return new Content(view: 'mail.module-notification', with: ['inboxUrl' => url(config('cms.admin_path', 'admin'))]);
    }
}
