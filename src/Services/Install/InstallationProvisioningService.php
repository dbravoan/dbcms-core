<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Install;

use DbCms\Core\Blocks\BlockPackageManager;
use DbCms\Core\Contracts\Services\InstallationEnvironmentServiceInterface;
use DbCms\Core\Contracts\Services\InstallationProvisioningServiceInterface;
use DbCms\Core\Database\Seeders\CmsFoundationSeeder;
use DbCms\Core\DTOs\Install\InstallationResultDTO;
use DbCms\Core\Modules\ModuleManager;
use DbCms\Core\Services\Security\AccessProvisioner;
use DbCms\Core\Services\Security\PassportKeyPermissionService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Laravel\Passport\ClientRepository;
use Symfony\Component\Console\Output\OutputInterface;

final class InstallationProvisioningService implements InstallationProvisioningServiceInterface
{
    public function __construct(
        private readonly PassportKeyPermissionService $keys,
        private readonly ClientRepository $clients,
        private readonly InstallationEnvironmentServiceInterface $environment,
        private readonly ModuleManager $modules,
        private readonly AccessProvisioner $access,
    ) {}

    public function provision(bool $withDemo = false, ?OutputInterface $output = null): InstallationResultDTO
    {
        $logs = [];
        $seeder = CmsFoundationSeeder::class;
        if ($withDemo) {
            $configured = ConfiguredDemoSeeder::resolve();
            if ($configured === null) {
                return new InstallationResultDTO(false, 'Este proyecto no incluye el contenido de demostración. Instala sin --with-demo.', $logs);
            }
            $seeder = $configured;
        }
        try {
            $this->command('migrate', ['--force' => true], $output);
            $logs[] = 'Tablas relacionales del núcleo migradas correctamente.';
            $this->modules->installConfiguredModules();
            $logs[] = 'Módulos seleccionados instalados; el resto permanece sin instalar.';
            $blocks = app(BlockPackageManager::class);
            foreach ((array) config('cms.block_packages.enabled', ['basic']) as $identifier) {
                if (is_string($identifier) && isset($blocks->all()[$identifier])) {
                    $blocks->setEnabled($identifier, true);
                }
            }
            $this->infrastructure($output);
            $logs[] = 'Claves OAuth2, cliente de acceso personal y almacenamiento preparados.';
            $this->command('db:seed', ['--class' => $seeder, '--force' => true], $output);
            $access = $this->access->provision();
            $logs[] = "Catálogo de permisos sincronizado: {$access['permissions']} permisos y {$access['roles']} roles base.";
            $logs[] = $withDemo ? 'Datos de demostración solicitados inicializados.' : 'Estructura base inicializada sin contenido de demostración.';
            $this->environment->write([
                'SESSION_DRIVER' => 'database',
                'CACHE_STORE' => 'database',
                'QUEUE_CONNECTION' => 'database',
            ]);
            $logs[] = 'Sesiones, caché y cola configuradas para usar la base de datos.';

            return new InstallationResultDTO(true, 'Aprovisionamiento completado con éxito.', $logs);
        } catch (\Throwable $error) {
            Log::error('Installer provisioning failed', [
                'exception' => $error::class,
                'message' => $error->getMessage(),
            ]);

            return new InstallationResultDTO(false, 'Error durante el aprovisionamiento. Revisa los logs del servidor.', $logs);
        }
    }

    private function infrastructure(?OutputInterface $output): void
    {
        $privateExists = File::isFile(storage_path('oauth-private.key'));
        $publicExists = File::isFile(storage_path('oauth-public.key'));
        if ($privateExists !== $publicExists) {
            throw new \RuntimeException('The existing OAuth key pair is incomplete; it will not be overwritten.');
        }
        if (! $privateExists && File::isFile(storage_path('installed'))) {
            throw new \RuntimeException('Restore the original OAuth keys of the installed site before continuing.');
        }
        if (! $privateExists) {
            $this->generateOAuthKeyPair();
            if (! File::isFile(storage_path('oauth-private.key')) || ! File::isFile(storage_path('oauth-public.key'))) {
                throw new \RuntimeException('OAuth keys were not created.');
            }
        }
        $this->verifyOAuthKeyPair();
        $this->keys->harden(storage_path());
        foreach (['oauth-private.key', 'oauth-public.key'] as $file) {
            clearstatcache(true, storage_path($file));
            if ((fileperms(storage_path($file)) & 0777) !== 0600) {
                throw new \RuntimeException('The OAuth key permissions are unsafe.');
            }
        }
        $provider = (string) config('auth.guards.api.provider', 'users');
        try {
            $this->clients->personalAccessClient($provider);
        } catch (\RuntimeException) {
            DB::transaction(fn () => $this->clients->createPersonalAccessGrantClient('dbCMS Personal Access Client', $provider));
        }
        if (! File::exists(public_path('storage')) && ! is_link(public_path('storage'))) {
            $this->command('storage:link', [], $output);
            $this->verifyStorageLink(public_path('storage'));
        }
    }

    private function generateOAuthKeyPair(): void
    {
        $key = openssl_pkey_new([
            'private_key_bits' => 4096,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        if ($key === false || ! openssl_pkey_export($key, $private)) {
            throw new \RuntimeException('OAuth private key generation failed.');
        }
        $details = openssl_pkey_get_details($key);
        if ($details === false || ! isset($details['key'])) {
            throw new \RuntimeException('OAuth public key generation failed.');
        }

        $this->writeKeyAtomically(storage_path('oauth-private.key'), $private, 0600);
        $this->writeKeyAtomically(storage_path('oauth-public.key'), $details['key'], 0600);
    }

    private function writeKeyAtomically(string $path, string $contents, int $permissions): void
    {
        $temporary = $path.'.tmp.'.bin2hex(random_bytes(8));
        if (file_put_contents($temporary, $contents, LOCK_EX) === false || ! chmod($temporary, $permissions) || ! rename($temporary, $path)) {
            @unlink($temporary);
            throw new \RuntimeException('OAuth key could not be persisted securely.');
        }
    }

    private function verifyOAuthKeyPair(): void
    {
        $private = @openssl_pkey_get_private(File::get(storage_path('oauth-private.key')));
        $public = @openssl_pkey_get_public(File::get(storage_path('oauth-public.key')));
        $privateDetails = $private === false ? false : openssl_pkey_get_details($private);
        $publicDetails = $public === false ? false : openssl_pkey_get_details($public);
        if ($privateDetails === false || $publicDetails === false || $privateDetails['type'] !== OPENSSL_KEYTYPE_RSA || $privateDetails['bits'] < 2048 || ! hash_equals($privateDetails['key'], $publicDetails['key'])) {
            throw new \RuntimeException('The existing OAuth key pair is invalid; it will not be overwritten.');
        }
    }

    private function verifyStorageLink(string $path): void
    {
        clearstatcache(true, $path);
        if (! is_link($path)) {
            throw new \RuntimeException('Public storage link was not created.');
        }
    }

    /** @param array<string, mixed> $arguments */
    private function command(string $name, array $arguments, ?OutputInterface $output): void
    {
        if (Artisan::call($name, $arguments, $output) !== 0) {
            throw new \RuntimeException('Installation command failed: '.$name);
        }
    }
}
