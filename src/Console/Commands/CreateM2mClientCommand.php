<?php

declare(strict_types=1);

namespace DbCms\Core\Console\Commands;

use DbCms\Core\Models\Role;
use DbCms\Core\Services\Auth\ServiceAccountProvisioningService;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class CreateM2mClientCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cms:create-m2m-client 
                            {--name=Autonomous M2M Agent : Name of the client application} 
                            {--role= : Required explicit existing CMS role slug for the service account}
                            {--scopes=mcp:access,mcp:read,mcp:write,mcp:destructive,m2m:service : Default OAuth2 scopes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Creates a Laravel Passport OAuth2 Machine-to-Machine client for client_credentials grant';

    /**
     * Execute the console command.
     */
    public function handle(ServiceAccountProvisioningService $provisioning): int
    {
        $name = (string) $this->option('name');
        $scopesRaw = (string) $this->option('scopes');
        $scopes = array_values(array_filter(array_map('trim', explode(',', $scopesRaw))));
        $scopeString = implode(' ', $scopes);
        $roleSlug = trim((string) $this->option('role'));
        if ($roleSlug === '') {
            $this->error('Specify --role=<existing CMS role slug>; no role or privileges are chosen automatically.');

            return self::FAILURE;
        }
        $role = Role::where('slug', $roleSlug)->first();
        if (! $role instanceof Role) {
            $this->error('The selected CMS role does not exist.');

            return self::FAILURE;
        }
        try {
            $client = $provisioning->create($name, $scopes, (int) $role->getKey());
        } catch (ValidationException $exception) {
            $this->error(implode(' ', array_merge(...array_values($exception->errors()))));

            return self::FAILURE;
        }
        $secret = $client->plainSecret;

        $this->newLine();
        $this->info('================================================================');
        $this->info('     CLIENTE OAUTH2 MACHINE-TO-MACHINE (M2M) CREADO CON ÉXITO   ');
        $this->info('================================================================');
        $this->line("<comment>Nombre:</comment>        {$name}");
        $this->line("<comment>Client ID:</comment>     {$client->id}");
        $this->line("<comment>Client Secret:</comment> {$secret}");
        $this->line("<comment>Scopes:</comment>        {$scopeString}");
        $this->line("<comment>CMS Role:</comment>      {$roleSlug}");
        $this->newLine();
        $this->info('Flujo de obtención de Token JWT vía RFC 6749 (client_credentials):');
        $this->line('curl -X POST http://localhost:8080/oauth/token \\');
        $this->line("  -H 'Content-Type: application/x-www-form-urlencoded' \\");
        $this->line("  -d 'grant_type=client_credentials&client_id={$client->id}&client_secret={$secret}&scope={$scopeString}'");
        $this->info('================================================================');
        $this->newLine();

        return self::SUCCESS;
    }
}
