<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Capabilities;

use DbCms\Core\Capabilities\ActorContext;
use DbCms\Core\Capabilities\CapabilityAuthorizer;
use DbCms\Core\Capabilities\ExecutionContext;
use DbCms\Core\Contracts\Services\ModuleNotificationServiceInterface;
use DbCms\Core\Models\CapabilityAuditLog;
use DbCms\Core\Services\Auth\TokenRevocationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Passport\Passport;

final class AccountActions
{
    public function __construct(private readonly ModuleNotificationServiceInterface $notifications, private readonly TokenRevocationService $tokens, private readonly CapabilityAuditLogger $auditLogger) {}

    /**
     * @return array<string, mixed>
     */
    public function notificationsMarkAllRead(): array
    {
        $updated = $this->notifications->markAllAsRead();

        return ['marked_read' => $updated];
    }

    /**
     * @return array<string, mixed>
     */
    public function tokenRevoke(string $id): array
    {
        $token = Passport::token()->find($id);

        if ($token === null) {
            throw ValidationException::withMessages(['id' => "No token found with id [{$id}]."]);
        }

        $this->tokens->forTokens([$id]);

        return ['id' => $id, 'revoked' => true];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function auditLog(array $args): array
    {
        $actor = app(ExecutionContext::class)->actor;
        if ($actor === null) {
            $id = Auth::guard('web')->id();
            $actor = new ActorContext($id === null ? null : (int) $id);
        }
        abort_unless(app(CapabilityAuthorizer::class)->allowed($actor, 'read', 'manage:modules'), 403);
        $limit = (int) ($args['limit'] ?? 20);
        $tool = isset($args['tool']) && is_string($args['tool']) && $args['tool'] !== '' ? $args['tool'] : null;
        $problemsOnly = filter_var($args['problems_only'] ?? false, FILTER_VALIDATE_BOOLEAN);

        return [
            'stats' => $this->auditLogger->stats(),
            'entries' => $this->auditLogger->recent($limit, $tool, $problemsOnly)
                ->map(static fn (CapabilityAuditLog $log): array => [
                    'id' => (int) $log->getKey(),
                    'tool' => (string) $log->getAttribute('tool'),
                    'status' => (string) $log->getAttribute('status'),
                    'ability' => (string) $log->getAttribute('ability'),
                    'permission' => $log->getAttribute('permission'),
                    'user' => $log->user?->email,
                    'is_m2m' => (bool) $log->getAttribute('is_m2m'),
                    'args' => $log->getAttribute('args'),
                    'error' => $log->getAttribute('error'),
                    'duration_ms' => $log->getAttribute('duration_ms'),
                    'created_at' => $log->getAttribute('created_at')?->toIso8601String(),
                ])
                ->all(),
        ];
    }
}
