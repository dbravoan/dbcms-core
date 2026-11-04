<?php

declare(strict_types=1);

namespace DbCms\Core\Console\Commands;

use DbCms\Core\Models\User;
use Illuminate\Console\Command;

class IssueMcpTokenCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cms:issue-mcp-token 
                            {--user= : User ID to issue token for (defaults to primary admin)} 
                            {--name=MCP Assistant : Descriptive name for the token} 
                            {--scopes=mcp:access,mcp:read,mcp:write,mcp:destructive : Comma-separated OAuth2 scopes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Issues a Laravel Passport Personal Access Token configured for MCP operations';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $userId = $this->option('user');
        $name = (string) $this->option('name');
        $scopesRaw = (string) $this->option('scopes');
        $scopes = array_filter(array_map('trim', explode(',', $scopesRaw)));

        /** @var User|null $user */
        $user = $userId ? User::find($userId) : User::first();

        if (! $user) {
            $this->error('No se encontró ningún usuario para emitir el token.');

            return self::FAILURE;
        }

        $tokenResult = $user->createToken($name, $scopes);

        $this->newLine();
        $this->info('================================================================');
        $this->info('  PERSONAL ACCESS TOKEN GENERADO CON ÉXITO (LARAVEL PASSPORT)   ');
        $this->info('================================================================');
        $this->line("<comment>Nombre:</comment>  {$name}");
        $this->line("<comment>Usuario:</comment> {$user->name} ({$user->email}) [ID: {$user->id}]");
        $this->line('<comment>Scopes:</comment>  '.implode(', ', $scopes));
        $this->newLine();
        $this->line('<comment>Bearer Access Token (Copia este token):</comment>');
        $this->line($tokenResult->accessToken);
        $this->newLine();
        $this->info('Ejemplo de uso en curl:');
        $this->line('curl -X POST http://localhost:8080/mcp/cms \\');
        $this->line("  -H 'Authorization: Bearer {$tokenResult->accessToken}' \\");
        $this->line("  -H 'Content-Type: application/json' \\");
        $this->line("  -d '{\"jsonrpc\":\"2.0\",\"id\":1,\"method\":\"tools/list\"}'");
        $this->info('================================================================');
        $this->newLine();

        return self::SUCCESS;
    }
}
