<?php

declare(strict_types=1);

namespace DbCms\Core\Mcp\Native\Tools;

use DbCms\Core\Capabilities\CapabilityRegistry;
use DbCms\Core\Mcp\Services\McpToolDispatcher;
use DbCms\Core\Models\User;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

/**
 * Bridges ONE dbCMS capability to the native `laravel/mcp` SDK.
 *
 * The capability (name, schema, permission, OAuth ability, audit) lives
 * in the core capability registry; this class only adapts it so standard MCP clients — Claude
 * Desktop, Cursor, VS Code, the MCP Inspector, OpenCode's MCP transport — can
 * call it. A single class serves every capability, so nothing is duplicated.
 */
final class CapabilityTool extends Tool
{
    /**
     * @param  array<string, mixed>  $inputSchema
     */
    public function __construct(
        private readonly string $capabilityName,
        private readonly string $capabilityDescription,
        private readonly array $inputSchema,
    ) {
        $this->name = $capabilityName;
        $this->description = $capabilityDescription;
    }

    /**
     * Native tool for a registered capability.
     */
    /** @param array<string,mixed>|null $entry */
    public static function for(string $name, ?array $entry = null): self
    {
        $capability = app(CapabilityRegistry::class)->get($name);

        if ($capability === null) {
            throw new \InvalidArgumentException("Unknown capability [{$name}].");
        }

        return new self(
            capabilityName: $capability->name(),
            capabilityDescription: $capability->description(),
            inputSchema: (array) ($entry['parameters'] ?? ['type' => 'object', 'properties' => $capability->properties(), 'required' => $capability->required()]),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $result = parent::toArray();
        $schema = $this->inputSchema;
        if (($schema['properties'] ?? []) === []) {
            $schema['properties'] = (object) [];
        }
        $result['inputSchema'] = $schema;

        return $result;
    }

    public function handle(Request $request): Response
    {
        $arguments = array_filter(
            (array) $request->all(),
            fn (mixed $value, mixed $key): bool => array_key_exists((string) $key, (array) ($this->inputSchema['properties'] ?? [])),
            ARRAY_FILTER_USE_BOTH,
        );

        $result = app(McpToolDispatcher::class)->dispatch($this->capabilityName, $arguments, self::context());

        if (isset($result['error']) && is_string($result['error'])) {
            return Response::error(json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        return Response::text((string) json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @return array<string, mixed>
     */
    private static function context(): array
    {
        $request = request();
        $user = $request->user() ?? auth()->user();

        return [
            'user' => $user instanceof User ? $user : null,
            'scopes' => array_values(array_map('strval', (array) $request->attributes->get('oauth_scopes', []))),
            'token_id' => is_string($request->attributes->get('oauth_token_id')) ? (string) $request->attributes->get('oauth_token_id') : null,
            'client_id' => is_string($request->attributes->get('oauth_client_id')) ? (string) $request->attributes->get('oauth_client_id') : null,
            'is_m2m' => (bool) $request->attributes->get('is_m2m', false),
            'ip' => $request->ip(),
        ];
    }
}
