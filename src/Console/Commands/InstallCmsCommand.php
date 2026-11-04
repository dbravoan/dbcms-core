<?php

declare(strict_types=1);

namespace DbCms\Core\Console\Commands;

use DbCms\Core\Contracts\Services\InstallationAdminServiceInterface;
use DbCms\Core\Contracts\Services\InstallationDatabaseServiceInterface;
use DbCms\Core\Contracts\Services\InstallationEnvironmentServiceInterface;
use DbCms\Core\Contracts\Services\InstallationProvisioningServiceInterface;
use DbCms\Core\DTOs\Install\DatabaseSetupDTO;
use DbCms\Core\DTOs\Install\InstallationAdminDTO;
use DbCms\Core\DTOs\Install\LegalCompanyDTO;
use DbCms\Core\Services\Install\BrandRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;

class InstallCmsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cms:install
        {--db-host= : Host del servidor MySQL}
        {--db-port=3306 : Puerto de conexión MySQL}
        {--db-database= : Nombre de la base de datos}
        {--db-username= : Usuario con privilegios en MySQL}
        {--db-password= : Contraseña de MySQL}
        {--with-demo : Siembra el sitio completo con datos de demostración}
        {--without-demo : Inicializa solo con tablas y roles base, sin datos demo}
        {--admin-name= : Nombre del Super-Administrador}
        {--admin-email= : Correo electrónico del Administrador}
        {--admin-password= : Contraseña de acceso (mínimo 8 caracteres)}
        {--admin-path=admin : Slug de la ruta de administración}
        {--company-file= : JSON file with reviewed company and privacy information}
        {--site-name=dbCMS Enterprise : Nombre del sitio web}
        {--brand= : Identificador de marca/backoffice (ej. dbcms, termocms, neutral)}
        {--theme= : Tema público del frontoffice (ej. db-cyber)}
        {--force : Sobrescribe la instalación previa si ya existe}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Instala y aprovisiona dbCMS en un entorno VPS o servidor de producción';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('======================================================================');
        $this->info(' >_ dbCMS // Instalador Automatizado de Servidor');
        $this->info('======================================================================');

        // 1. Check if already installed
        if (File::exists(storage_path('installed')) && ! $this->option('force')) {
            $this->warn('El sistema ya se encuentra instalado. Utiliza --force para reinstalar.');

            return self::FAILURE;
        }

        // 2. Ensure .env and APP_KEY
        $this->line('[1/7] Verificando archivo de configuración y clave de cifrado...');
        try {
            $this->ensureEnvFile();
            $adminData = $this->resolveAdminAccount();
            $companyPath = (string) $this->option('company-file');
            if ($companyPath === '' || ! is_file($companyPath)) {
                throw new \InvalidArgumentException('--company-file must identify a reviewed company JSON file.');
            }
            $companyValues = json_decode((string) file_get_contents($companyPath), true, flags: JSON_THROW_ON_ERROR);
            if (! is_array($companyValues)) {
                throw new \InvalidArgumentException('Company JSON must be an object.');
            }
            $company = LegalCompanyDTO::fromArray($companyValues);
        } catch (\InvalidArgumentException|\RuntimeException|\JsonException|ValidationException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        // 3. Collect Database Configuration
        $this->line('[2/7] Configurando conexión con base de datos MySQL...');
        $dbConfig = $this->resolveDatabaseCredentials();

        try {
            app(InstallationDatabaseServiceInterface::class)->save(new DatabaseSetupDTO(...$dbConfig));
        } catch (\Throwable) {
            $this->error('No fue posible preparar la conexión MySQL y su configuración.');

            return self::FAILURE;
        }

        $seedDemo = (bool) $this->option('with-demo');
        if ($this->option('without-demo')) {
            $seedDemo = false;
        } elseif (! $this->option('with-demo') && ! $this->hasOptionValue('db-host')) {
            $seedDemo = $this->confirm('¿Deseas sembrar datos de demostración?', false);
        }
        $result = app(InstallationProvisioningServiceInterface::class)->provision($seedDemo, $this->output);
        foreach ($result->logs as $line) {
            $this->line($line);
        }
        if (! $result->success) {
            $this->error($result->message);

            return self::FAILURE;
        }

        // 7. Administrator Account Setup
        $this->line('[6/7] Creando cuenta de Super-Administrador...');
        $siteName = (string) ($this->option('site-name') ?: 'dbCMS Enterprise');
        $adminPath = (string) ($this->option('admin-path') ?: 'admin');

        // Brand (backoffice theme) + front-office theme, validated against disk.
        /** @var BrandRegistry $brandRegistry */
        $brandRegistry = app(BrandRegistry::class);

        $brand = (string) ($this->option('brand') ?: config('cms.admin_theme', 'dbcms'));
        $allowedBrands = $brandRegistry->brandIdentifiers();
        if (! in_array($brand, $allowedBrands, true)) {
            $this->warn("Marca [{$brand}] no encontrada en disco. Usando [".config('cms.admin_theme', 'dbcms').'].');
            $brand = (string) config('cms.admin_theme', 'dbcms');
        }

        $frontTheme = (string) ($this->option('theme') ?: config('cms.theme', 'db-cyber'));
        $allowedThemes = $brandRegistry->frontThemeIdentifiers();
        if ($allowedThemes !== [] && ! in_array($frontTheme, $allowedThemes, true)) {
            $this->warn("Tema [{$frontTheme}] no encontrado en disco. Usando [".config('cms.theme', 'db-cyber').'].');
            $frontTheme = (string) config('cms.theme', 'db-cyber');
        }

        try {
            app(InstallationAdminServiceInterface::class)->finalize(new InstallationAdminDTO($adminData['name'], $adminData['email'], $adminData['password'], $siteName, $adminPath, $brand, $frontTheme, company: $company), (bool) $this->option('force'));
        } catch (\Throwable) {
            $this->error('No se pudo finalizar la instalación de forma coherente.');

            return self::FAILURE;
        }

        // Summary Table
        $this->newLine();
        $this->info('======================================================================');
        $this->info(' ¡dbCMS ha sido instalado exitosamente!');
        $this->info('======================================================================');
        $this->table(
            ['Parámetro', 'Valor'],
            [
                ['Nombre del Sitio', $siteName],
                ['Ruta Backoffice', "/{$adminPath}"],
                ['Marca / Tema Backoffice', $brand],
                ['Tema Público (Front)', $frontTheme],
                ['URL de Acceso', url("/{$adminPath}/login")],
                ['Correo Administrador', $adminData['email']],
                ['Datos Demo Sembrados', $seedDemo ? 'Sí (Completo)' : 'No (Limpio)'],
                ['Endpoint MCP para Agentes', url('/mcp/cms')],
            ]
        );

        return self::SUCCESS;
    }

    /**
     * Resolves database credentials from options or interactive questions.
     *
     * @return array{host: string, port: string, database: string, username: string, password: string}
     */
    protected function resolveDatabaseCredentials(): array
    {
        $host = (string) ($this->option('db-host') ?: (config('database.connections.mysql.host') ?: '127.0.0.1'));
        $port = (string) ($this->option('db-port') ?: (config('database.connections.mysql.port') ?: '3306'));
        $database = (string) ($this->option('db-database') ?: (config('database.connections.mysql.database') ?: 'dbcms'));
        $username = (string) ($this->option('db-username') ?: (config('database.connections.mysql.username') ?: 'root'));
        $password = (string) ($this->option('db-password') ?? (config('database.connections.mysql.password') ?? ''));

        // If not running in automated flags mode, confirm interactively
        if (! $this->hasOptionValue('db-host')) {
            $host = (string) $this->ask('Host de MySQL', $host);
            $port = (string) $this->ask('Puerto de MySQL', $port);
            $database = (string) $this->ask('Nombre de la base de datos', $database);
            $username = (string) $this->ask('Usuario de MySQL', $username);
            $password = (string) ($this->secret('Contraseña de MySQL') ?? '');
        }

        return [
            'host' => $host,
            'port' => $port,
            'database' => $database,
            'username' => $username,
            'password' => $password,
        ];
    }

    /**
     * Resolves administrator credentials from options or interactive questions.
     *
     * @return array{name: string, email: string, password: string}
     */
    protected function resolveAdminAccount(): array
    {
        $name = (string) ($this->option('admin-name') ?: 'CMS Administrator');
        $email = (string) ($this->option('admin-email') ?: '');
        $password = (string) ($this->option('admin-password') ?: '');

        if (! $this->hasOptionValue('admin-email')) {
            if ($this->option('no-interaction') || ! $this->input->isInteractive()) {
                throw new \InvalidArgumentException('Indica el correo del administrador con --admin-email para una instalación sin preguntas.');
            }
            $name = (string) $this->ask('Nombre del Administrador', $name);
            $email = (string) $this->ask('Correo electrónico del Administrador', $email);
            if (empty($password)) {
                $password = (string) $this->secret('Contraseña del Administrador (mínimo 8 caracteres)');
            }
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || strlen($password) < 8) {
            throw new \InvalidArgumentException('Indica un correo válido y una contraseña de administrador de al menos 8 caracteres.');
        }

        return [
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ];
    }

    /**
     * Checks whether an option was explicitly provided.
     */
    protected function hasOptionValue(string $key): bool
    {
        return ! empty($this->option($key));
    }

    /**
     * Ensures .env file exists and has an encryption APP_KEY.
     */
    protected function ensureEnvFile(): void
    {
        app(InstallationEnvironmentServiceInterface::class)->ensure($this->output);
    }

    /** @param array<string, string> $values */
    protected function updateEnvFile(array $values): void
    {
        app(InstallationEnvironmentServiceInterface::class)->write($values);
    }
}
