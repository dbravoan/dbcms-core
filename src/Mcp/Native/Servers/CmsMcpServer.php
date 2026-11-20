<?php

declare(strict_types=1);

namespace DbCms\Core\Mcp\Native\Servers;

use DbCms\Core\Capabilities\AvailableCapabilities;
use DbCms\Core\Mcp\Native\Tools\CapabilityTool;
use DbCms\Core\Services\Capabilities\ActorContextFactory;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Server\Tool;

/** Publishes the shared capability catalog through the standard MCP protocol. */
#[Version('1.0.0')]
class CmsMcpServer extends Server
{
    protected string $name = 'CMS';

    protected string $version = '1.0.0';

    protected string $instructions = 'Exposes every backoffice operation as a capability.';

    public int $defaultPaginationLength = 100;

    // The SDK clamps the default to this maximum, so keep both aligned.
    public int $maxPaginationLength = 100;

    /**
     * Native tools built from the capability registry, cached per request.
     *
     * @var array<int, Tool>|null
     */
    private ?array $resolvedTools = null;

    /**
     * The SDK builds ServerContext with $this->tools; filling it here is what
     * turns the capability registry into native MCP tools.
     *
     * The advertised name and instructions follow the active product brand
     * (`config('cms.name')`), never a hardcoded one.
     */
    public function createContext(): ServerContext
    {
        $brand = (string) config('cms.name', 'CMS');

        $this->name = $brand;
        $this->instructions = $brand.' exposes every backoffice operation as a capability. '
            .'Start with cms_capabilities to see what you may do, then cms_entity_types and cms_resource_types '
            .'for the fields you can write. For Blade pages discover blade_templates in cms_entity_types and set attributes.template to its key. Write text fields as plain strings and rich_text fields as {format, content} in translations.<locale>.template_data. Discover supported formats in the schema. Re-read with cms_entity_get. For pages and posts using sections, plan a purpose for each section. Run cms_block_types for the current layouts, project and module blocks and active modules in the target entity, language and market. Use cms_block_templates for copyable published templates and cms_block_references for eligible data in the selected block schemas. '
            .'Save a draft with sections_json, then read the composition with cms_content_sections_get. Use its version and layout for targeted changes through cms_content_sections_patch. Use sync only for a deliberate full replacement. Publishing, deletion and external changes return an execution reference for human approval in the backoffice. Everything '
            .'you do is recorded in the audit trail (cms_mcp_audit).';

        $this->tools = $this->capabilityTools();

        return parent::createContext();
    }

    /**
     * Builds (and caches) the native tool list from the capability registry.
     *
     * @return array<int, Tool>
     */
    public function capabilityTools(): array
    {
        if ($this->resolvedTools !== null) {
            return $this->resolvedTools;
        }

        $actor = app(ActorContextFactory::class)->fromRequest(request(), 'mcp');
        $manifest = app(AvailableCapabilities::class)->manifest($actor);
        // Only an explicit constrained-client request narrows the registered
        // catalog. The SDK paginates the complete authorized default catalog.
        $requestedLimit = request()->input('limit');
        if ($requestedLimit) {
            $manifest = array_slice($manifest, 0, max(1, min(count($manifest), (int) $requestedLimit)));
        }

        $tools = [];

        foreach ($manifest as $entry) {
            $tools[] = CapabilityTool::for((string) $entry['name'], $entry);
        }

        return $this->resolvedTools = $tools;
    }
}
