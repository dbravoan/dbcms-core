<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Install;

use DbCms\Core\Contracts\Services\InstallationDatabaseServiceInterface;
use DbCms\Core\Contracts\Services\InstallationEnvironmentServiceInterface;
use DbCms\Core\DTOs\Install\DatabaseSetupDTO;
use DbCms\Core\DTOs\Install\InstallationResultDTO;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use PDO;

final class InstallationDatabaseService implements InstallationDatabaseServiceInterface
{
    public function __construct(private readonly InstallationEnvironmentServiceInterface $environment) {}

    public function test(DatabaseSetupDTO $database): InstallationResultDTO
    {
        try {
            $this->connect($database, true);

            return new InstallationResultDTO(true, "¡Conexión establecida correctamente con la base de datos '{$database->database}'!");
        } catch (\PDOException $error) {
            $unknownDatabase = ($error->errorInfo[1] ?? null) === 1049;
            if ($database->autoCreate && $unknownDatabase) {
                try {
                    $connection = $this->connect($database, false);
                    if ($connection->exec("CREATE DATABASE IF NOT EXISTS `{$database->database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci") === false) {
                        throw new \RuntimeException('Database creation failed.');
                    }
                    $this->connect($database, true);

                    return new InstallationResultDTO(true, "La base de datos '{$database->database}' fue creada y su conexión verificada.", created: true);
                } catch (\Throwable $creationError) {
                    Log::warning('Installer database creation failed', ['exception' => $creationError::class]);

                    return new InstallationResultDTO(false, 'La base de datos no existe y no pudo ser creada automáticamente. Revisa las credenciales y los permisos del usuario MySQL.');
                }
            }
            Log::warning('Installer database connection failed', ['exception' => $error::class]);

            return new InstallationResultDTO(false, 'No se pudo conectar a MySQL. Revisa host, puerto, usuario y contraseña.');
        }
    }

    public function save(DatabaseSetupDTO $database): void
    {
        $result = $this->test($database);
        if (! $result->success) {
            throw ValidationException::withMessages(['database' => $result->message]);
        }
        $this->environment->write([
            'DB_CONNECTION' => 'mysql', 'DB_HOST' => $database->host, 'DB_PORT' => $database->port,
            'DB_DATABASE' => $database->database, 'DB_USERNAME' => $database->username, 'DB_PASSWORD' => $database->password,
        ]);
        config(['database.connections.mysql.host' => $database->host, 'database.connections.mysql.port' => $database->port, 'database.connections.mysql.database' => $database->database, 'database.connections.mysql.username' => $database->username, 'database.connections.mysql.password' => $database->password]);
        DB::purge('mysql');
    }

    private function connect(DatabaseSetupDTO $database, bool $selectDatabase): PDO
    {
        $dsn = 'mysql:host='.$database->host.';port='.$database->port.';'.($selectDatabase ? 'dbname='.$database->database.';' : '').'charset=utf8mb4';

        return new PDO($dsn, $database->username, $database->password, [PDO::ATTR_TIMEOUT => 3, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    }
}
