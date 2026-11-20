<?php

declare(strict_types=1);

use DbCms\Core\Http\Middleware\AuthenticateMcpPassport;
use DbCms\Core\Mcp\Http\Middleware\EnsureMcpEnabled;
use DbCms\Core\Mcp\Native\Servers\CmsMcpServer;
use Laravel\Mcp\Facades\Mcp;

/*
|--------------------------------------------------------------------------
| Native MCP server (Model Context Protocol)
|--------------------------------------------------------------------------
| Exposes the dbCMS capability surface over the standard MCP protocol, so any
| client that speaks `initialize` + `tools/list` + `tools/call` can connect:
|
|   Endpoint: POST /mcp/cms          (Streamable HTTP)
|   Auth:     Bearer <Passport token> with mcp:access + mcp:read/write/destructive
|
| Tips: `POST /mcp/cms?limit=8` publishes a subset (handy for clients that need a
| short tool list); the capability layer always allows `cms_capabilities`.
*/
Mcp::web('mcp/cms', CmsMcpServer::class)
    ->middleware([EnsureMcpEnabled::class, AuthenticateMcpPassport::class.':mcp:access', 'throttle:mcp']);
