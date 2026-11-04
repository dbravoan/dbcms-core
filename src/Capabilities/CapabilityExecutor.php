<?php

declare(strict_types=1);

namespace DbCms\Core\Capabilities;

use DbCms\Core\Capabilities\Contracts\CapabilityAuditInterface;
use DbCms\Core\Capabilities\Contracts\CapabilityExecutorInterface;
use DbCms\Core\Capabilities\Contracts\PendingOperationInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

final class CapabilityExecutor implements CapabilityExecutorInterface
{
    public function __construct(private readonly CapabilityRegistry $registry, private readonly AvailableCapabilities $available, private readonly CapabilityAuthorizer $authorizer, private readonly ArgumentValidator $validator, private readonly ExecutionContext $context, private readonly CapabilityAuditInterface $audit) {}

    /**
     * @param  array<string,mixed>  $args
     * @return array<string,mixed>
     */
    public function execute(string $name, array $args, ActorContext $actor, bool $approved = false): array
    {
        $capability = $this->registry->get($name);
        $reason = $this->available->reason($name, $actor, $args);
        $audit = ['tool' => $name, 'args' => $args, 'user' => $this->authorizer->user($actor), 'token_id' => $actor->tokenId, 'client_id' => $actor->clientId, 'is_m2m' => $actor->isService, 'ability' => $capability?->ability() ?? 'read', 'permission' => $capability?->permissionFor($args)];
        if ($reason !== null || $capability === null) {
            $this->log($audit + ['status' => 'denied', 'error' => $reason]);

            $denied = $capability !== null && ! $this->authorizer->allowed($actor, $capability->ability(), $capability->permissionFor($args));

            return ['error' => $reason, 'status' => $denied ? 'denied' : 'unavailable', $denied ? 'denied' : 'unavailable' => true];
        }
        $effect = $this->registry->metadata($name)['effect'];
        $attributes = (array) ($args['attributes'] ?? []);
        $publishing = ! empty($args['is_published']) || ! empty($args['is_active']) || ! empty($args['publish']) || ! empty($attributes['publish']) || ! empty($attributes['is_published']) || ! empty($attributes['is_active']);
        if (! $approved && (in_array($effect, ['publish', 'delete', 'external'], true) || $publishing)) {
            $this->log($audit + ['status' => 'confirmation_required']);

            return ['confirmation_required' => true, 'action' => $capability->description(), 'error' => 'Se requiere aprobación humana del cambio concreto.'];
        }
        unset($args['confirm'], $args['approval_id']);
        $previousActor = $this->context->actor;
        $previousArguments = $this->context->arguments;
        $previousName = $this->context->capabilityName;
        $previousUser = Auth::user();
        $user = $this->authorizer->user($actor);
        if ($user === null) {
            return ['error' => 'Identidad no disponible.', 'denied' => true];
        }
        $this->context->actor = $actor;
        $this->context->arguments = $args;
        $this->context->capabilityName = $name;
        Auth::setUser($user);
        try {
            $this->validator->validate($capability, $args);
            $result = $capability->handle($args);
            $this->log($audit + ['status' => 'ok']);

            return $result;
        } catch (ValidationException $e) {
            $this->log($audit + ['status' => 'error', 'error' => 'Validation failed']);

            return ['error' => 'Validation failed.', 'validation' => $e->errors()];
        } catch (Throwable $e) {
            if ($e instanceof PendingOperationInterface) {
                return ['pending' => true, 'provider' => $e->provider(), 'provider_request_id' => $e->requestId()];
            }
            if ($e instanceof HttpException && in_array($e->getStatusCode(), [403, 404, 409], true)) {
                $status = match ($e->getStatusCode()) {
                    403 => 'denied', 409 => 'conflict', default => 'unavailable'
                };
                $this->log($audit + ['status' => $status, 'error' => 'Operation refused']);

                return ['error' => match ($status) {
                    'denied' => 'Permisos insuficientes.', 'conflict' => 'El contenido cambió. Revisa la versión actual.', default => 'El recurso ya no está disponible.'
                }, 'status' => $status, $status => true];
            }
            Log::warning('Capability execution failed', ['capability' => $name, 'exception_class' => $e::class]);
            $this->log($audit + ['status' => 'error', 'error' => 'Capability execution failed']);

            return ['error' => 'La operación no pudo completarse.', 'failed' => true, 'uncertain' => in_array($effect, ['generation', 'external'], true)];
        } finally {
            $this->context->actor = $previousActor;
            $this->context->arguments = $previousArguments;
            $this->context->capabilityName = $previousName;
            if ($previousUser !== null) {
                Auth::setUser($previousUser);
            } else {
                Auth::forgetGuards();
            }
        }
    }

    /**
     * @param  array<string,mixed>  $context
     */
    private function log(array $context): void
    {
        try {
            $this->audit->log($context);
        } catch (Throwable $e) {
            Log::warning('Capability audit unavailable', ['exception_class' => $e::class]);
        }
    }
}
