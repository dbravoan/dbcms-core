<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Adapters\Orchestration;

use DbCms\Core\Capabilities\AvailableCapabilities;
use DbCms\Core\Capabilities\DTOs\OrchestrationRequest;
use DbCms\Core\Contracts\Services\ExecutionServiceInterface;
use DbCms\Core\Jobs\Orchestration\RunExecution;
use DbCms\Core\Services\Capabilities\ActorContextFactory;
use Illuminate\Http\Request;

final class ExecutionTransport
{
    public function __construct(private readonly ExecutionServiceInterface $executions, private readonly ActorContextFactory $actors, private readonly AvailableCapabilities $available) {}

    /**
     * @return array<string,mixed>
     */
    public function start(Request $request): array
    {
        $data = $request->validate(['instruction' => 'required|string|max:16000', 'idempotency_key' => 'required|string|max:128', 'editor_context' => 'nullable|array', 'editor_context.*' => 'nullable|string|max:1000', 'market' => 'nullable|string|max:10', 'locale' => 'nullable|string|max:10']);
        $input = new OrchestrationRequest((string) $data['instruction'], $this->actors->fromRequest($request, $this->origin($request)), (string) $data['idempotency_key'], (array) ($data['editor_context'] ?? []));
        $execution = $this->executions->start($input->instruction, $input->actor, $input->idempotencyKey, $input->editorContext);
        if ($execution->status === 'pending') {
            RunExecution::dispatch((string) $execution->id);
        }

        return $execution->toArray();
    }

    /**
     * @return array<string,mixed>
     */
    public function show(Request $request, string $execution): array
    {
        return $this->executions->owned($execution, $this->actors->fromRequest($request, $this->origin($request)))->toArray();
    }

    /**
     * @return array<string,mixed>
     */
    public function respond(Request $request, string $execution): array
    {
        $data = $request->validate(['answer' => 'required|string|max:8000']);
        $record = $this->executions->answer($execution, $this->actors->fromRequest($request, $this->origin($request)), (string) $data['answer']);
        if ($record->status === 'pending') {
            RunExecution::dispatch((string) $record->id);
        }

        return $record->toArray();
    }

    /**
     * @return array<string,mixed>
     */
    public function approve(Request $request, string $execution): array
    {
        $data = $request->validate(['step' => 'required|string|max:64', 'binding_hash' => 'required|string|size:64']);
        $record = $this->executions->approve($execution, $this->actors->fromRequest($request), (string) $data['step'], (string) $data['binding_hash']);
        RunExecution::dispatch((string) $record->id);

        return $record->toArray();
    }

    /**
     * @return array<string,mixed>
     */
    public function resume(Request $request, string $execution): array
    {
        $record = $this->executions->resume($execution, $this->actors->fromRequest($request, $this->origin($request)));
        if ($record->status === 'pending') {
            RunExecution::dispatch((string) $record->id);
        }

        return $record->toArray();
    }

    /**
     * @return array<string,mixed>
     */
    public function cancel(Request $request, string $execution): array
    {
        return $this->executions->cancel($execution, $this->actors->fromRequest($request, $this->origin($request)))->toArray();
    }

    /**
     * @return array<string,mixed>
     */
    public function catalog(Request $request): array
    {
        $actor = $this->actors->fromRequest($request, $this->origin($request));

        return ['capabilities' => $this->available->manifest($actor), 'unavailable' => $this->available->unavailable($actor)];
    }

    private function origin(Request $request): string
    {
        return $request->is('api/*') ? 'api' : 'web';
    }
}
