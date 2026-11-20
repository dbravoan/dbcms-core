<?php

declare(strict_types=1);

namespace DbCms\Core\Capabilities\Tools;

use DbCms\Core\Capabilities\CapabilityAuthorizer;
use DbCms\Core\Capabilities\Contracts\CapabilityInterface;
use DbCms\Core\Capabilities\ExecutionContext;
use DbCms\Core\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Shared plumbing for MCP tools: schema projection and ability helpers.
 */
abstract class AbstractCapabilityTool implements CapabilityInterface
{
    public const ABILITY_READ = 'read';

    public const ABILITY_WRITE = 'write';

    public const ABILITY_DESTRUCTIVE = 'destructive';

    /**
     * @param  array<string, mixed>  $args
     */
    public function permissionFor(array $args): ?string
    {
        return $this->permission();
    }

    public function mutating(): bool
    {
        return $this->ability() !== self::ABILITY_READ;
    }

    public function destructive(): bool
    {
        return $this->ability() === self::ABILITY_DESTRUCTIVE;
    }

    /**
     * JSON-schema descriptor advertised by `tools/list`.
     *
     * @return array{name: string, description: string, parameters: array<string, mixed>}
     */
    public function schema(): array
    {
        $properties = $this->properties();

        return [
            'name' => $this->name(),
            'description' => $this->description(),
            'parameters' => array_filter([
                'type' => 'object',
                'properties' => $properties,
                'required' => $this->required(),
            ], static fn (mixed $value): bool => $value !== []),
        ];
    }

    /**
     * Metadata used by the backoffice panel and by the audit trail.
     *
     * @return array{name: string, description: string, permission: string|null, ability: string, mutating: bool, destructive: bool, required: list<string>}
     */
    public function metadata(): array
    {
        return [
            'name' => $this->name(),
            'description' => $this->description(),
            'permission' => $this->permission(),
            'ability' => $this->ability(),
            'mutating' => $this->mutating(),
            'destructive' => $this->destructive(),
            'required' => $this->required(),
        ];
    }

    /**
     * Resolves a permission that depends on the arguments; a failing resolution
     * (unknown type, missing resource) falls back to the strictest permission so
     * the call is denied instead of crashing before authorization.
     *
     * @param  callable(): (string|null)  $resolve
     */
    protected function safePermission(callable $resolve): ?string
    {
        try {
            return $resolve();
        } catch (\Throwable) {
            return 'access:admin';
        }
    }

    /**
     * User behind the call: the request resolver set by the Passport middleware
     * (or the authenticated session when a capability runs from the panel).
     */
    protected function actor(): ?User
    {
        $context = app(ExecutionContext::class)->actor;
        $user = $context !== null ? app(CapabilityAuthorizer::class)->user($context) : Auth::user();

        return $user instanceof User ? $user : null;
    }

    /**
     * @param  array<string, mixed>  $args
     */
    protected function string(array $args, string $key, string $default = ''): string
    {
        $value = $args[$key] ?? $default;

        return is_scalar($value) ? (string) $value : $default;
    }

    /**
     * @param  array<string, mixed>  $args
     */
    protected function int(array $args, string $key, int $default = 0): int
    {
        $value = $args[$key] ?? $default;

        return is_numeric($value) ? (int) $value : $default;
    }

    /**
     * @param  array<string, mixed>  $args
     */
    protected function bool(array $args, string $key, bool $default = false): bool
    {
        $value = $args[$key] ?? $default;

        if (is_bool($value)) {
            return $value;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    protected function array(array $args, string $key): array
    {
        $value = $args[$key] ?? [];

        return is_array($value) ? $value : [];
    }
}
