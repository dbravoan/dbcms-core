<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Orchestration;

use DbCms\Core\Capabilities\ActorContext;
use DbCms\Core\Capabilities\ArgumentValidator;
use DbCms\Core\Capabilities\AvailableCapabilities;
use DbCms\Core\Capabilities\CapabilityAuthorizer;
use DbCms\Core\Capabilities\CapabilityExecutor;
use DbCms\Core\Capabilities\CapabilityRegistry;
use DbCms\Core\Capabilities\Contracts\PlannerInterface;
use DbCms\Core\Capabilities\DTOs\ExecutionPlan;
use DbCms\Core\Capabilities\DTOs\ExecutionStep as StepData;
use DbCms\Core\Capabilities\DTOs\StepResult;
use DbCms\Core\Capabilities\PlanValidator;
use DbCms\Core\Contracts\Services\ExecutionServiceInterface;
use DbCms\Core\Jobs\Orchestration\RunExecution;
use DbCms\Core\Models\Orchestration\Execution;
use DbCms\Core\Models\Orchestration\ExecutionStep;
use DbCms\Core\Services\Privacy\LegalPageService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

final class ExecutionService implements ExecutionServiceInterface
{
    public function __construct(private readonly CapabilityExecutor $executor, private readonly AvailableCapabilities $available, private readonly CapabilityRegistry $registry, private readonly ResourceFingerprint $fingerprints) {}

    /**
     * @param  array<string,mixed>  $context
     */
    public function start(string $instruction, ActorContext $actor, string $key, array $context = []): Execution
    {
        if (! app()->bound(PlannerInterface::class)) {
            throw new HttpException(503, 'La orquestación está desactivada.');
        }
        if (! app(CapabilityAuthorizer::class)->allowed($actor, 'write', 'use:ai')) {
            throw new HttpException(403, 'No tienes permiso para iniciar tareas IA.');
        }
        $hash = hash('sha256', json_encode([$instruction, $context, $actor->market, $actor->locale], JSON_THROW_ON_ERROR));
        $existing = Execution::where('user_id', $actor->userId)->where('idempotency_key', $key)->first();
        if ($existing !== null) {
            if ($existing->request_hash !== $hash) {
                throw new HttpException(409, 'La clave identifica otra petición.');
            }

            return $this->owned((string) $existing->id, $actor);
        }
        app(LegalPageService::class)->requireProcessing('executions', true);
        $execution = Execution::firstOrCreate(['user_id' => $actor->userId, 'idempotency_key' => $key], ['id' => (string) Str::uuid(), 'request_hash' => $hash, 'instruction' => $instruction, 'actor' => $actor->toArray(), 'editor_context' => $context, 'version' => 1]);
        if (! $execution->wasRecentlyCreated) {
            if ($execution->request_hash !== $hash) {
                throw new HttpException(409, 'La clave identifica otra petición.');
            }

            return $this->owned((string) $execution->id, $actor);
        }
        $this->plan($execution, $actor);

        return $execution->refresh()->load('steps');
    }

    /**
     * @param  array<string,mixed>  $args
     */
    public function startCapability(string $name, array $args, ActorContext $actor): Execution
    {
        $capability = $this->registry->get($name);
        if ($capability === null) {
            throw ValidationException::withMessages(['capability' => 'Capacidad desconocida.']);
        }
        app(ArgumentValidator::class)->validate($capability, $args, true);
        $key = 'direct:'.hash('sha256', json_encode([$name, $args, $actor->tokenId, $actor->clientId, $actor->market, $actor->locale, $this->fingerprints->hash($name, $args, 1)], JSON_THROW_ON_ERROR));
        app(LegalPageService::class)->requireProcessing('executions', true);
        $execution = Execution::firstOrCreate(['user_id' => $actor->userId, 'idempotency_key' => $key], ['id' => (string) Str::uuid(), 'request_hash' => hash('sha256', $key), 'instruction' => $name, 'actor' => $actor->toArray(), 'status' => 'pending', 'version' => 1]);
        if ($execution->wasRecentlyCreated) {
            $this->savePlan($execution, new ExecutionPlan([new StepData('action', $name, $args, [])], $capability->description()));
        } else {
            $this->owned((string) $execution->id, $actor);
        }
        $this->run((string) $execution->id);

        return $execution->refresh()->load('steps');
    }

    public function owned(string $id, ActorContext $actor): Execution
    {
        $execution = Execution::findOrFail($id);
        $stored = ActorContext::fromArray((array) $execution->actor);
        $reviewService = $stored->isService && $actor->origin === 'web'
            && app(CapabilityAuthorizer::class)->allowed($actor, 'write', 'manage:ai')
            && app(CapabilityAuthorizer::class)->allowed($actor, 'write', 'manage:users');
        if ($actor->userId === null || ((int) $execution->user_id !== $actor->userId && ! $reviewService)) {
            throw new HttpException(403, 'Ejecución privada.');
        }
        if ($actor->origin !== 'web' && $stored->clientId !== null && $stored->clientId !== $actor->clientId) {
            throw new HttpException(403, 'Cliente distinto.');
        }
        $policy = app(CapabilityAuthorizer::class);
        foreach ($execution->steps()->get() as $step) {
            $capability = $this->registry->get($step->capability);
            $args = $step->resolved_arguments ?? $step->arguments;
            if ($capability !== null && ! $policy->allowed($actor, $capability->ability(), $capability->permissionFor($args))) {
                throw new HttpException(403, 'Ya no tienes permiso para consultar esta operación.');
            }
        }
        if ($execution->steps()->count() === 0 && ! $policy->allowed($actor, 'read', 'use:ai')) {
            throw new HttpException(403, 'Ya no tienes permiso para consultar esta tarea IA.');
        }

        return $execution->load('steps');
    }

    public function answer(string $id, ActorContext $actor, string $answer): Execution
    {
        $execution = $this->owned($id, $actor);
        app(LegalPageService::class)->requireProcessing('executions', true);
        $execution = DB::transaction(function () use ($execution, $answer): Execution {
            $execution = Execution::whereKey($execution->id)->lockForUpdate()->firstOrFail();
            if ($execution->status !== 'needs_input') {
                throw new HttpException(409, 'La ejecución no está esperando información.');
            }
            $execution->update(['instruction' => $execution->instruction."\nAclaración: ".$answer, 'version' => (int) $execution->version + 1, 'status' => 'planning']);

            return $execution;
        });
        $this->plan($execution, $actor, continuation: true);

        return $execution->refresh()->load('steps');
    }

    public function approve(string $id, ActorContext $actor, string $stepKey, string $binding): Execution
    {
        if ($actor->origin !== 'web' || $actor->isService) {
            throw new HttpException(403, 'Aprueba desde el backoffice con tu sesión.');
        }
        $execution = $this->owned($id, $actor);

        app(LegalPageService::class)->requireProcessing('executions', true);

        return DB::transaction(function () use ($execution, $actor, $stepKey, $binding): Execution {
            $execution = Execution::whereKey($execution->id)->lockForUpdate()->firstOrFail();
            if ($execution->status !== 'approval_required') {
                throw new HttpException(409, 'La ejecución ya no está esperando aprobación.');
            }
            $step = $execution->steps()->where('key', $stepKey)->firstOrFail();
            $args = (array) $step->resolved_arguments;
            if ($step->status !== 'approval_required' || $binding !== $step->binding_hash || $binding !== $this->fingerprints->hash((string) $step->capability, $args, (int) $execution->version)) {
                throw new HttpException(409, 'El contenido o la solicitud han cambiado. Vuelve a revisar.');
            }
            $capability = $this->registry->get((string) $step->capability);
            if ($capability === null || ! app(CapabilityAuthorizer::class)->allowed($actor, $capability->ability(), $capability->permissionFor($args))) {
                throw new HttpException(403, 'No puedes aprobar esta operación.');
            }
            if ($this->available->reason((string) $step->capability, ActorContext::fromArray((array) $execution->actor), $args) !== null) {
                throw new HttpException(403, 'Autorización revocada.');
            }
            DB::table('cms_execution_approvals')->updateOrInsert(['step_id' => $step->id, 'binding_hash' => $binding], ['user_id' => $actor->userId, 'actor_user_id' => $execution->user_id, 'plan_version' => $execution->version, 'approved_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            $step->update(['status' => 'pending']);
            $execution->update(['status' => 'pending']);

            return $execution->refresh()->load('steps');
        });
    }

    public function resume(string $id, ActorContext $actor): Execution
    {
        $this->owned($id, $actor);
        [$execution, $replan] = DB::transaction(function () use ($id, $actor): array {
            $execution = Execution::whereKey($id)->lockForUpdate()->firstOrFail();
            if (in_array($execution->status, ['cancelled', 'completed', 'uncertain'], true)) {
                return [$execution, false];
            }
            app(LegalPageService::class)->requireProcessing('executions', true);
            if ($actor->origin !== 'web') {
                $execution->fill(['actor' => $actor->toArray(), 'version' => (int) $execution->version + 1]);
            }
            $replan = in_array($execution->status, ['failed', 'planning'], true) && ! $execution->steps()->exists();
            if ($execution->status === 'failed' && ! $replan) {
                $execution->steps()->where('status', 'failed')->update(['status' => 'pending']);
            }
            $execution->fill(['status' => $replan ? 'planning' : 'pending'])->save();

            return [$execution, $replan];
        });

        if ($replan) {
            $this->plan($execution, ActorContext::fromArray($execution->actor));
        }

        return $execution->refresh()->load('steps');
    }

    public function cancel(string $id, ActorContext $actor): Execution
    {
        $execution = $this->owned($id, $actor);
        $execution->update(['status' => 'cancelled']);

        return $execution->refresh()->load('steps');
    }

    public function run(string $id): void
    {
        $lock = Cache::lock('cms-execution:'.$id, 1260);
        if (! $lock->get()) {
            return;
        }
        try {
            $this->runLocked($id);
        } finally {
            $lock->release();
        }
    }

    private function runLocked(string $id): void
    {
        $execution = Execution::findOrFail($id);
        if (in_array($execution->status, ['cancelled', 'completed', 'needs_input', 'uncertain', 'planning'], true)) {
            return;
        }
        app(LegalPageService::class)->requireProcessing('executions', true);
        $actor = ActorContext::fromArray((array) $execution->actor);
        foreach ($execution->steps()->get() as $step) {
            $execution->refresh();
            if ($execution->status === 'cancelled') {
                return;
            }
            if ($step->status === 'completed') {
                continue;
            }
            if ($step->status === 'running' || $step->status === 'uncertain') {
                $this->updateActiveExecution($execution, ['status' => 'uncertain', 'error' => 'Hay una operación iniciada con resultado incierto; no se repetirá automáticamente.']);

                return;
            }
            try {
                if ($step->status === 'pending_remote') {
                    $args = (array) $step->resolved_arguments;
                    $args['provider_request_id'] = $step->result['provider_request_id'] ?? $args['provider_request_id'] ?? null;
                    $args['provider'] = $step->result['provider'] ?? $args['provider'] ?? null;
                } elseif (! empty($step->resolved_arguments['provider_request_id'])) {
                    // A failed authorization or polling attempt cannot erase a remote checkpoint.
                    $args = (array) $step->resolved_arguments;
                } else {
                    $args = $this->resolve((array) $step->arguments, $execution);
                }
            } catch (HttpException $error) {
                $step->update(['status' => 'failed', 'error' => $error->getMessage()]);
                $this->updateActiveExecution($execution, ['status' => 'failed', 'error' => $error->getMessage()]);

                return;
            }
            $binding = $this->fingerprints->hash((string) $step->capability, $args, (int) $execution->version);
            $approval = DB::table('cms_execution_approvals')->where('step_id', $step->id)->where('actor_user_id', $actor->userId)->where('binding_hash', $binding)->where('plan_version', $execution->version)->whereNull('consumed_at')->first();
            $claimed = ExecutionStep::whereKey($step->id)->whereIn('status', ['pending', 'approval_required', 'pending_remote'])->update(['status' => 'running', 'resolved_arguments' => json_encode($args), 'started_at' => now()]);
            if ($claimed !== 1) {
                return;
            }
            $step->refresh();
            if (! $this->updateActiveExecution($execution, ['status' => 'running'])) {
                return;
            }
            try {
                // No remote HTTP request happens inside a database transaction.
                $execute = fn (): array => $this->executor->execute((string) $step->capability, $args, $actor, $approval !== null);
                $effect = $this->registry->metadata($step->capability)['effect'];
                $localMutation = in_array($effect, ['publish', 'delete'], true) || str_starts_with($step->capability, 'cms_entity_') || str_starts_with($step->capability, 'cms_resource_');
                $result = $approval !== null && $localMutation
                    ? $this->fingerprints->guardLocalMutation($step->capability, $args, (int) $execution->version, $binding, $execute)
                    : $execute();
                $outcome = StepResult::fromPayload($result);
                if ($outcome->status === 'approval_required') {
                    $result['review'] = $this->fingerprints->preview($step->capability, $args);
                    $step->update(['status' => 'approval_required', 'binding_hash' => $this->fingerprints->hash($step->capability, $args, (int) $execution->version), 'result' => $result]);
                    $this->updateActiveExecution($execution, ['status' => 'approval_required']);

                    return;
                }
                if ($outcome->status === 'pending_remote') {
                    $checkpoint = array_replace($args, ['provider_request_id' => $result['provider_request_id'] ?? null, 'provider' => $result['provider'] ?? null]);
                    $step->update(['status' => 'pending_remote', 'result' => $result, 'resolved_arguments' => $checkpoint]);
                    if ($this->updateActiveExecution($execution, ['status' => 'pending_remote'])) {
                        RunExecution::dispatch((string) $execution->id)->delay(now()->addSeconds(30));
                    }

                    return;
                }
                if ($outcome->status === 'needs_input') {
                    $step->update(['status' => 'needs_input', 'result' => $result, 'error' => $result['error'] ?? null]);
                    $this->updateActiveExecution($execution, ['status' => 'needs_input', 'question' => $result['question'] ?? '¿Puedes completar los datos de esta operación?', 'error' => null]);

                    return;
                }
                if ($outcome->status !== 'completed') {
                    $status = $outcome->status === 'uncertain' ? 'uncertain' : 'failed';
                    $error = $result['error'] ?? 'La operación no pudo completarse.';
                    $step->update(['status' => $status, 'result' => $result, 'error' => $error]);
                    $this->updateActiveExecution($execution, ['status' => $status, 'error' => $error]);

                    return;
                }
                DB::transaction(function () use ($step, $result, $approval): void {
                    $step->update(['status' => 'completed', 'result' => $result, 'finished_at' => now(), 'error' => null]);
                    if ($approval !== null) {
                        DB::table('cms_execution_approvals')->where('id', $approval->id)->update(['consumed_at' => now()]);
                    }
                });
            } catch (Throwable $error) {
                Log::warning('Persisted capability interrupted', ['capability' => $step->capability, 'exception_class' => $error::class, 'file' => $error->getFile(), 'line' => $error->getLine()]);
                $step->update(['status' => 'uncertain', 'error' => 'Resultado de ejecución incierto.']);
                $this->updateActiveExecution($execution, ['status' => 'uncertain']);

                return;
            }
        }
        $this->updateActiveExecution($execution, ['status' => 'completed', 'error' => null]);
    }

    /** A late worker result may retain its checkpoint, but never revive a cancellation.
     *
     * @param  array<string,mixed>  $attributes
     */
    private function updateActiveExecution(Execution $execution, array $attributes): bool
    {
        Execution::whereKey($execution->id)->where('status', '!=', 'cancelled')->update($attributes);

        return $execution->refresh()->status !== 'cancelled';
    }

    private function plan(Execution $execution, ActorContext $actor, bool $continuation = false): void
    {
        if (! app()->bound(PlannerInterface::class)) {
            $this->updateActiveExecution($execution, ['status' => $continuation ? 'needs_input' : 'failed', 'error' => 'Configura un modelo de texto y activa el asistente IA.']);

            return;
        }
        try {
            $context = (array) $execution->editor_context;
            if ($continuation) {
                $context['execution_progress'] = [
                    'continuation_rule' => 'Retain completed steps as an unchanged prefix, including their IDs, capabilities, arguments and dependencies. Revise only the remaining steps. Existing remote checkpoints cannot be replaced.',
                    'steps' => $execution->steps()->get()->map(fn (ExecutionStep $step): array => ['id' => $step->key, 'capability' => $step->capability, 'arguments' => $step->arguments, 'depends_on' => $step->dependencies, 'status' => $step->status, 'result' => $step->result, 'resolved_arguments' => $step->resolved_arguments])->all(),
                ];
            }
            $plan = app(PlannerInterface::class)->plan((string) $execution->instruction, $actor, $context);
            app(PlanValidator::class)->validate($plan->toArray(), $this->registry->names());
            $this->savePlan($execution, $plan);
        } catch (Throwable $error) {
            $this->updateActiveExecution($execution, ['status' => $continuation ? 'needs_input' : 'failed', 'error' => $error instanceof ValidationException || $error instanceof InvalidArgumentException ? 'El plan propuesto no es válido. Conserva los pasos ya ejecutados y corrige únicamente los pendientes.' : 'No se pudo planificar. Revisa la conexión y el modelo de texto.']);
        }
    }

    private function savePlan(Execution $execution, ExecutionPlan $plan): void
    {
        foreach ($plan->steps as $step) {
            $capability = $this->registry->get($step->capability);
            if ($capability === null) {
                throw ValidationException::withMessages(['capability' => 'Capacidad desconocida.']);
            }
            app(ArgumentValidator::class)->validate($capability, $step->arguments, true);
        }
        DB::transaction(function () use ($execution, $plan): void {
            $current = Execution::whereKey($execution->id)->lockForUpdate()->firstOrFail();
            if ($current->status === 'cancelled') {
                return;
            }
            if ($current->version !== $execution->version) {
                throw ValidationException::withMessages(['plan' => 'La ejecución cambió mientras se preparaba la continuación.']);
            }
            $existing = $current->steps()->lockForUpdate()->get();
            $completed = $existing->takeWhile(fn (ExecutionStep $step): bool => $step->status === 'completed');
            if ($completed->count() !== $existing->where('status', 'completed')->count()) {
                throw ValidationException::withMessages(['plan' => 'El historial completado no se puede reordenar.']);
            }
            if ($plan->question !== null) {
                $storedPlan = $plan->toArray();
                $storedPlan['steps'] = $existing->map(fn (ExecutionStep $step): array => ['id' => $step->key, 'capability' => $step->capability, 'arguments' => $step->arguments, 'depends_on' => $step->dependencies])->all();
                $current->update(['plan' => $storedPlan, 'status' => 'needs_input', 'question' => $plan->question, 'error' => null]);

                return;
            }
            $preserved = $completed->keyBy('key');
            foreach ($existing as $position => $stored) {
                $checkpoint = ! empty($stored->resolved_arguments['provider_request_id']) || ! empty($stored->result['provider_request_id']);
                if (in_array($stored->status, ['running', 'uncertain', 'pending_remote'], true) || $checkpoint || $stored->status === 'completed') {
                    $candidate = $plan->steps[$position] ?? null;
                    if ($candidate === null || $candidate->id !== $stored->key || $candidate->capability !== $stored->capability || ! $this->sameArguments($candidate->arguments, $stored->arguments) || $candidate->dependsOn !== $stored->dependencies || in_array($stored->status, ['running', 'uncertain'], true)) {
                        throw ValidationException::withMessages(['plan' => 'La continuación debe conservar el historial ejecutado y sus checkpoints.']);
                    }
                    $preserved->put($stored->key, $stored);
                }
            }
            $current->steps()->whereNotIn('id', $preserved->pluck('id')->all())->delete();
            $current->update(['plan' => $plan->toArray(), 'status' => 'pending', 'question' => null, 'error' => null]);
            foreach ($plan->steps as $position => $step) {
                if (($stored = $preserved->get($step->id)) !== null) {
                    if ($stored->status !== 'completed') {
                        $stored->update(['status' => 'pending_remote']);
                    }

                    continue;
                }
                $current->steps()->create(['key' => $step->id, 'position' => $position, 'capability' => $step->capability, 'arguments' => $step->arguments, 'dependencies' => $step->dependsOn]);
            }
        });
    }

    /** JSON object field order may change in storage; scalar values and list order may not. */
    private function sameArguments(mixed $left, mixed $right): bool
    {
        if (! is_array($left) || ! is_array($right)) {
            return $left === $right;
        }
        if (array_is_list($left) !== array_is_list($right) || count($left) !== count($right)) {
            return false;
        }
        foreach ($left as $key => $value) {
            if (! array_key_exists($key, $right) || ! $this->sameArguments($value, $right[$key])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string,mixed>  $args
     * @return array<string,mixed>
     */
    private function resolve(array $args, Execution $execution): array
    {
        foreach ($args as $key => $value) {
            if (! is_array($value)) {
                continue;
            }
            if (isset($value['$ref'])) {
                [$source, $path] = array_pad(explode('.', (string) $value['$ref'], 2), 2, '');
                $step = $execution->steps()->where('key', $source)->where('status', 'completed')->first();
                if ($step === null) {
                    throw new HttpException(409, 'Dependencia no completada.');
                }
                $missing = new \stdClass;
                $resolved = $path === '' ? $step->result : data_get($step->result, $path, $missing);
                if ($resolved === $missing) {
                    throw new HttpException(409, 'El resultado anterior no contiene el campo solicitado.');
                }
                $args[$key] = $resolved;
            } else {
                $args[$key] = $this->resolve($value, $execution);
            }
        }

        return $args;
    }
}
