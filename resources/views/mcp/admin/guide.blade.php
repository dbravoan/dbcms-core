<div class="space-y-6 pt-4">
    <x-admin.form-card class="space-y-4">
        <x-admin.form-header title="Autenticación Exclusiva con Laravel Passport" />
        <p class="text-sm text-[color:var(--admin-muted)]">{!! __('admin.mcp.oauth_scope_desc') !!}</p>
        <p class="text-sm text-[color:var(--admin-muted)]">Emite un token desde la gestión central y selecciona únicamente los scopes que necesite el cliente.</p>
        @can('manage:tokens')
            <div class="flex flex-wrap gap-3">
                <x-admin.button :href="route('admin.tokens.create')" variant="primary" icon="fa-solid fa-key">Emitir token Passport</x-admin.button>
                <x-admin.button :href="route('admin.tokens.index')" variant="ghost">Ver tokens</x-admin.button>
            </div>
        @endcan
    </x-admin.form-card>

    {{-- Guía de Agentes Externos --}}
    <x-admin.form-card class="space-y-5">
        <div>
            <h3 class="text-sm font-bold text-[color:var(--admin-text)] flex items-center gap-2">
                <i class="fa-solid fa-terminal text-[color:var(--admin-accent)]"></i> Guía de Conexión para Agentes Externos
            </h3>
            <p class="text-[color:var(--admin-muted)] text-xs mt-1">
                {!! __('admin.mcp.agents_desc') !!}
            </p>
            <p class="text-[color:var(--admin-muted)] text-[11px]">{!! __('admin.mcp.transport_hint', ['endpoint' => url('/mcp/cms')]) !!}</p>
        </div>

        {{-- Tabs de Clientes --}}
        <div x-data="{ activeTab: 'opencode' }" class="space-y-4">
            <div class="flex items-center gap-2 border-b border-[color:var(--admin-border)] pb-2">
                <x-admin.button variant="ghost" size="sm" alpine-active="activeTab === 'opencode'" @click="activeTab = 'opencode'">
                    <span class="text-xs">⚡</span> OpenCode (Recomendado)
                </x-admin.button>
                <x-admin.button variant="ghost" size="sm" alpine-active="activeTab === 'cursor'" @click="activeTab = 'cursor'">
                    <span>🖱️</span> Cursor IDE
                </x-admin.button>
                <x-admin.button variant="ghost" size="sm" alpine-active="activeTab === 'claude'" @click="activeTab = 'claude'">
                    <span>🤖</span> Claude Desktop
                </x-admin.button>
                <x-admin.button variant="ghost" size="sm" alpine-active="activeTab === 'antigravity'" @click="activeTab = 'antigravity'">
                    <span>🪐</span> {{ config('cms.name') }} CLI
                </x-admin.button>
            </div>

            {{-- Tab 1: OpenCode --}}
            <div x-show="activeTab === 'opencode'" class="space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-[color:var(--admin-text)] font-bold">{!! __('admin.mcp.file_opencode') !!}</span>
                    <x-admin.button variant="ghost" size="sm" type="button" x-data="{ copied: false }" @click="navigator.clipboard.writeText(document.getElementById('opencodeConfig').innerText).then(() => copied = true).catch(() => copied = false)" x-bind:aria-label="copied ? 'Copiado' : 'Copiar'">
                        <span x-text="copied ? '¡Copiado!' : 'Copiar Configuración'">Copiar Configuración</span>
                    </x-admin.button>
                </div>
                <pre id="opencodeConfig" class="p-4 rounded-lg bg-[color:var(--admin-surface)]/70 border border-[color:var(--admin-border)] text-[color:var(--admin-text)] overflow-x-auto select-all leading-relaxed">{
  "$schema": "https://opencode.ai/config.json",
  "mcp": {
    "dbCMS": {
      "type": "remote",
      "url": "{{ url('/mcp/cms') }}",
      "enabled": true,
      "oauth": false,
      "headers": {
        "Authorization": "Bearer TU_PASSPORT_TOKEN_AQUI"
      }
    }
  }
}</pre>
                <p class="text-[color:var(--admin-muted)] text-[11px]">
                    {!! __('admin.mcp.token_hint') !!}
                    <a href="https://opencode.ai/docs/mcp-servers/" target="_blank" rel="noopener noreferrer" class="text-[color:var(--admin-accent)] underline">OpenCode: documentación MCP</a>
                </p>
            </div>

            {{-- Tab 2: Cursor --}}
            <div x-show="activeTab === 'cursor'" class="space-y-3" style="display: none;">
                <div class="flex items-center justify-between">
                    <span class="text-[color:var(--admin-text)] font-bold">{!! __('admin.mcp.file_cursor') !!}</span>
                    <x-admin.button variant="ghost" size="sm" type="button" x-data="{ copied: false }" @click="navigator.clipboard.writeText(document.getElementById('cursorConfig').innerText).then(() => copied = true).catch(() => copied = false)" x-bind:aria-label="copied ? 'Copiado' : 'Copiar'">
                        <span x-text="copied ? '¡Copiado!' : 'Copiar Configuración'">Copiar Configuración</span>
                    </x-admin.button>
                </div>
                <pre id="cursorConfig" class="p-4 rounded-lg bg-[color:var(--admin-surface)]/70 border border-[color:var(--admin-border)] text-[color:var(--admin-text)] overflow-x-auto select-all leading-relaxed">{
  "mcpServers": {
    "dbCMS": {
      "url": "{{ url('/mcp/cms') }}",
      "headers": {
        "Authorization": "Bearer TU_PASSPORT_TOKEN_AQUI"
      }
    }
  }
}</pre>
            </div>

            {{-- Tab 3: Claude Desktop --}}
            <div x-show="activeTab === 'claude'" class="space-y-3" style="display: none;">
                <div class="flex items-center justify-between">
                    <span class="text-[color:var(--admin-text)] font-bold">{!! __('admin.mcp.file_claude') !!}</span>
                    <x-admin.button variant="ghost" size="sm" type="button" x-data="{ copied: false }" @click="navigator.clipboard.writeText(document.getElementById('claudeConfig').innerText).then(() => copied = true).catch(() => copied = false)" x-bind:aria-label="copied ? 'Copiado' : 'Copiar'">
                        <span x-text="copied ? '¡Copiado!' : 'Copiar Configuración'">Copiar Configuración</span>
                    </x-admin.button>
                </div>
                <p class="text-[color:var(--admin-muted)] text-[11px]">{!! __('admin.mcp.bridge_hint') !!} <a href="https://github.com/punkpeye/mcp-remote" target="_blank" rel="noopener noreferrer" class="text-[color:var(--admin-accent)] underline">mcp-remote: documentación</a></p>
                <pre id="claudeConfig" class="p-4 rounded-lg bg-[color:var(--admin-surface)]/70 border border-[color:var(--admin-border)] text-[color:var(--admin-text)] overflow-x-auto select-all leading-relaxed">{
  "mcpServers": {
    "dbCMS": {
      "command": "npx",
      "args": [
        "-y",
        "mcp-remote",
        "{{ url('/mcp/cms') }}",
        "--header",
        "Authorization:${AUTH_HEADER}"
      ],
      "env": {
        "AUTH_HEADER": "Bearer TU_PASSPORT_TOKEN_AQUI"
      }
    }
  }
}</pre>
            </div>

            {{-- Tab 4: generic CLI --}}
            <div x-show="activeTab === 'antigravity'" class="space-y-3" style="display: none;">
                <div class="flex items-center justify-between">
                    <span class="text-[color:var(--admin-text)] font-bold">{!! __('admin.mcp.config_gemini', ['app' => config('cms.name')]) !!}</span>
                    <x-admin.button variant="ghost" size="sm" type="button" x-data="{ copied: false }" @click="navigator.clipboard.writeText(document.getElementById('antigravityConfig').innerText).then(() => copied = true).catch(() => copied = false)" x-bind:aria-label="copied ? 'Copiado' : 'Copiar'">
                        <span x-text="copied ? '¡Copiado!' : 'Copiar Configuración'">Copiar Configuración</span>
                    </x-admin.button>
                </div>
                <pre id="antigravityConfig" class="p-4 rounded-lg bg-[color:var(--admin-surface)]/70 border border-[color:var(--admin-border)] text-[color:var(--admin-text)] overflow-x-auto select-all leading-relaxed">{
  "name": "dbCMS",
  "url": "{{ url('/mcp/cms') }}",
  "headers": {
    "Authorization": "Bearer TU_PASSPORT_TOKEN_AQUI"
  }
}</pre>
            </div>
        </div>
    </x-admin.form-card>
</div>
