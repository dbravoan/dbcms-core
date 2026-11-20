<?php

declare(strict_types=1);

namespace DbCms\Core\Forms\Services;

use DbCms\Core\Contracts\Services\ModuleNotificationServiceInterface;
use DbCms\Core\Forms\Jobs\DeliverFormAction;
use DbCms\Core\Forms\Models\CustomForm;
use DbCms\Core\Forms\Models\FormSubmission;
use DbCms\Core\Forms\Models\FormSubmissionAction;
use DbCms\Core\Services\Cms\CookieConsentService;
use DbCms\Core\Services\Privacy\LegalPageService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

final class FormActionService
{
    /** @param list<array<string,mixed>> $actions */
    public function record(CustomForm $form, FormSubmission $submission, array $actions): void
    {
        $actions[] = ['id' => '_notification', 'type' => 'notification'];
        foreach ($actions as $action) {
            $remote = in_array($action['type'], ['email', 'webhook', 'notification'], true);
            if ($action['type'] === 'webhook') {
                $action['configuration_revision'] = app(FormWebhookPolicy::class)->revision((string) $action['target']);
                $action['consent_receipt_id'] = request()->cookie(CookieConsentService::COOKIE);
            }
            if ($action['type'] === 'email') {
                $action['recipient'] = $form->notification_email;
            }
            $record = $submission->actions()->create([
                'action_key' => $action['id'], 'definition' => $action,
                'status' => $remote ? 'pending' : 'succeeded', 'completed_at' => $remote ? null : now(),
            ]);
            if ($remote) {
                $id = $record->id;
                DB::afterCommit(static function () use ($id): void {
                    try {
                        DeliverFormAction::dispatch($id);
                    } catch (\Throwable) {
                        // The durable record is retried by forms:deliver. A failed
                        // integration never turns a committed lead into an HTTP failure.
                    }
                });
            }
        }
    }

    /** @return list<array{type:string,target?:string}> */
    public function browserActions(FormSubmission $submission): array
    {
        $actions = [];
        foreach ($submission->actions()->where('status', 'succeeded')->get() as $record) {
            $definition = $record->definition;
            if (in_array($definition['type'], ['confirm', 'redirect', 'open_popup', 'close_popup'], true)) {
                $action = ['type' => (string) $definition['type']];
                if (is_string($definition['target'] ?? null)) {
                    $action['target'] = $definition['target'];
                }
                $actions[] = $action;
            }
        }

        return $actions;
    }

    public function deliver(int $id): void
    {
        $action = DB::transaction(static function () use ($id): ?FormSubmissionAction {
            $candidate = FormSubmissionAction::query()->find($id);
            if ($candidate === null || FormSubmission::query()->lockForUpdate()->find($candidate->form_submission_id) === null) {
                return null;
            }
            $action = FormSubmissionAction::query()->lockForUpdate()->find($id);
            if ($action === null || ! in_array($action->status, ['pending', 'processing'], true)
                || ($action->status === 'processing' && $action->updated_at?->isAfter(now()->subMinutes(5)))) {
                return null;
            }
            if ($action->attempts >= 3) {
                $action->update(['status' => 'failed', 'failure_code' => 'attempts_exhausted', 'completed_at' => now()]);

                return null;
            }
            $action->update(['status' => 'processing', 'attempts' => $action->attempts + 1]);

            return $action;
        });
        if ($action === null) {
            return;
        }
        try {
            $submission = $action->submission;
            $form = $submission->form;
            if (! $form->is_active
                || ! app(LegalPageService::class)->canCollect('forms')) {
                $this->finish($action, 'skipped');

                return;
            }
            $definition = $action->definition;
            $sent = true;
            if ($definition['type'] === 'notification') {
                if (app()->bound(ModuleNotificationServiceInterface::class)) {
                    app(ModuleNotificationServiceInterface::class)->notify(
                        module: 'forms', type: 'lead', title: 'Nuevo Lead: '.$form->slug,
                        message: 'Se ha recibido un nuevo contacto a través del formulario #'.$form->slug.'.',
                        actionUrl: route('admin.forms.submissions', ['form_id' => $form->id]),
                        data: ['submission_id' => $submission->id, 'form_id' => $form->id, 'locale' => $submission->locale],
                        sendEmail: filter_var($form->notification_email, FILTER_VALIDATE_EMAIL) !== false && ! collect($form->actions ?? [])->contains('type', 'email'),
                    );
                }
            } elseif ($definition['type'] === 'email') {
                $recipient = $definition['recipient'] ?? null;
                if (! is_string($recipient) || $recipient !== $form->notification_email || filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
                    $sent = false;
                } else {
                    Mail::raw(json_encode($submission->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                        static function ($message) use ($recipient, $form): void {
                            $message->to($recipient)->subject('Formulario: '.$form->slug);
                        });
                }
            } elseif ($definition['type'] === 'webhook') {
                $sent = app(FormWebhookPolicy::class)->send($definition, $submission->payload, 'form-action-'.$action->id);
            }
            $this->finish($action, $sent ? 'succeeded' : 'skipped');
        } catch (\Throwable) {
            $action->update(['status' => $action->attempts >= 3 ? 'failed' : 'pending', 'failure_code' => 'delivery_failed', 'completed_at' => $action->attempts >= 3 ? now() : null]);
            // Never put receiver response bodies, credentials or submitted data in logs.
            throw new \RuntimeException('Form action delivery failed.');
        }
    }

    private function finish(FormSubmissionAction $action, string $status): void
    {
        $action->update(['status' => $status, 'failure_code' => null, 'completed_at' => now()]);
    }
}
